<?php

/**
 * Enhanced file upload Plasmature types for Reason.
 * 
 * @package reason
 * @subpackage classes
 * @author Eric Naeseth <enaeseth+reason@gmail.com>
 */

reason_require_once('classes/entity.php');
reason_require_once('classes/head_items.php');
reason_require_once('function_libraries/asset_functions.php');
reason_require_once('function_libraries/image_tools.php');
reason_require_once('function_libraries/upload.php');
require_once CARL_UTIL_INC.'basic/cleanup_funcs.php';
include_once( DISCO_INC.'plasmature/plasmature.php' );

if (!defined('REASON_FLASH_UPLOAD_URI')) {
	define('REASON_FLASH_UPLOAD_URI', REASON_HTTP_BASE_PATH.'flash_upload/');
}

$GLOBALS['_disco_upload_session'] = null;
$GLOBALS['_disco_upload_session_sent'] = false;
$GLOBALS['_disco_upload_head_items_shown'] = false;

// There is some unavoidable code repetition here, due to PHP 4/5's lack of
// either traits (mixins) or multiple inheritance. If PHP 6 does implement the
// horizontal reuse RFC and we drop support for PHP 5, this code can be
// reorganized, and you should probably celebrate such a fantastic occurrence
// if you haven't already thoroughly done so.

/**
 * Reason asset uploads.
 */
class ReasonUploadType extends uploadType
{
	var $type = "ReasonUpload";
	var $type_valid_args = array('authenticator', 'existing_entity',
		'head_items');

	/** @access private */
	var $upload_sid = null;
	/** @access private */
	var $head_items;
	/** @access private */
	var $existing_entity;

	function additional_init_actions($args=array())
	{
		$auth = @$args['authenticator'];
		$this->upload_sid = _get_disco_async_upload_session($auth);
		
		$constraints = array(
			'mime_type' => $this->acceptable_types,
			'extension' => $this->acceptable_extensions,
			'max_size' => $this->max_file_size
		);
		reason_add_async_upload_constraints($this->upload_sid, $this->name,
			$constraints);
		
		if (isset($args["head_items"])) {
			$this->get_head_items($args["head_items"]);
		} else {
			_embed_plupload_stylesheet();
		}
		
		_reason_upload_handle_entity($this, "asset",
			"reason_get_asset_filesystem_location", "reason_get_asset_url");
		
		return parent::additional_init_actions($args);
	}

	function _get_upload_display($current, $add_text=null, $replace_text=null) {
		return _get_plupload_dom_stubs($this->_can_add_file($current), $current, $this->name, $add_text, $replace_text);
	}

	function get_display() {
		$current = $this->_get_current_file_info();

		// marker - reason upload element
		return _get_plupload_js_setup_snippet($this->upload_sid, $this->name) . 
			$this->_get_hidden_display($current).
			$this->_get_restriction_display($current).
			$this->_get_current_file_display($current).
			$this->_get_upload_display($current);
	}
	
	function register_fields()
	{
		return array_merge(_get_disco_async_upload_internal_field_names(),
			parent::register_fields());
	}
	
	function _get_uploaded_file()
	{
		// echo "trying to get uploaded file with name [" . $this->name . "], sid [" . $this->upload_sid . "]<BR>";
		$uploaded_file = _reason_get_disco_uploaded_file($this->name, $this->upload_sid);
		// echo "<PRE>"; var_dump($uploaded_file); echo "</PRE>";
		return $uploaded_file;
	}
	
	function get_head_items(&$head)
	{
		_populate_flash_upload_head_items($head);
		_embed_plupload_stylesheet($head);
	}
	
	function _get_hidden_display($current)
	{
		return _ensure_async_upload_head_items_shown().
			_get_disco_async_upload_hidden_fields($this->upload_sid).
			parent::_get_hidden_display($current);
	}
	
	function _get_current_file_display($current)
	{
		if (!$current) {
			// force an (empty) current file display to be shown
			$current = new stdClass;
			$current->path = false;
		}
		return parent::_get_current_file_display($current);
	}
}

/**
 * Reason image uploads.
 */
class ReasonImageUploadType extends image_uploadType
{
	var $type = "ReasonImageUpload";
	var $type_valid_args = array('authenticator', 'existing_entity',
		'head_items', 'obey_no_resize_flag', 'convert_to_image');
	
	/**
	 * Set this flag to true if this upload type should obey the
	 * "do_not_resize" POST flag.
	 * @var boolean
	 */
	var $obey_no_resize_flag = false;
	
	/**
	 * Set this flag to false if you want to prevent conversion of files (like .pdf and .tiff) to .png
	 */
	var $convert_to_image = true;
	
	/** @access private */
	var $upload_sid = null;
	/** @access private */
	var $head_items;
	/** @access private */
	var $existing_entity;

	function get_display() {
		$current = $this->_get_current_file_info();

		// marker - image upload element
		return _get_plupload_js_setup_snippet($this->upload_sid, $this->name) . 
			$this->_get_hidden_display($current).
			$this->_get_restriction_display($current).
			$this->_get_current_file_display($current).
			$this->_get_upload_display($current);
	}

	function _get_upload_display($current, $add_text=null, $replace_text=null) {
		return _get_plupload_dom_stubs($this->_can_add_file($current), $current, $this->name, $add_text, $replace_text);
	}

	function additional_init_actions($args=array())
	{
		$auth = @$args['authenticator'];
		$this->upload_sid = _get_disco_async_upload_session($auth);
		
		$constraints = array(
			'mime_type' => $this->acceptable_types,
			'max_file_size' => $this->max_file_size
		);
		if ($this->resize_image) {
			$constraints['max_dimensions'] = array($this->max_width,
				$this->max_height);
		}
		
		if ($this->convert_to_image) {
			$constraints['convert_to_image'] = true;
		}
		
		// echo "SETTING CONSTRAINTS FOR [" . $this->upload_sid . "]/[" . $this->name . "]...<P><PRE>"; var_dump($constraints); echo "</PRE>";
		reason_add_async_upload_constraints($this->upload_sid, $this->name,
			$constraints);
			
		if (isset($args["head_items"])) {
			$this->get_head_items($args["head_items"]);
		} else {
			_embed_plupload_stylesheet();
		}
		
		_reason_upload_handle_entity($this, "image",
		    "reason_get_image_path", "reason_get_image_url");

		return parent::additional_init_actions($args);
	}
	
	function register_fields()
	{
		return array_merge(_get_disco_async_upload_internal_field_names(),
			parent::register_fields());
	}
	
	function _get_uploaded_file()
	{
		$vars = $this->get_request();
		return _reason_get_disco_uploaded_file($this->name, $this->upload_sid,
			isset($vars['do_not_resize']) && $this->obey_no_resize_flag);
	}
	
	function _upload_success($path, $url)
	{
		if (!empty($this->file) && !empty($this->file["original_path"])) {
			$orig_path = $this->file["original_path"];
			if ($orig_path != $this->file["path"]) {
				$this->original_path = $orig_path;
			}
		}
		return parent::_upload_success($path, $url);
	}
	
	function _needs_resizing($image_path)
	{
		// XXX: super hacky; potential for abuse
		$vars = $this->get_request();
		return (isset($vars['do_not_resize']))
			? false
			: parent::_needs_resizing($image_path);
	}
	
	function get_head_items(&$head)
	{
		_populate_flash_upload_head_items($head);
		_embed_plupload_stylesheet($head);
	}
	
	function _get_hidden_display($current)
	{
		return _ensure_async_upload_head_items_shown().
			_get_disco_async_upload_hidden_fields($this->upload_sid).
			parent::_get_hidden_display($current);
	}
	
	function _get_current_file_display($current)
	{
		if (!$current) {
			// force an (empty, hidden) current image display to be shown
			$current = new stdClass;
			$current->path = false;
		}
		return parent::_get_current_file_display($current);
	}
}

/**
 * Reason image uploads with cropping.
 */
class ReasonImageUploadCroppableType extends ReasonImageUploadType
{
	var $type = "ReasonImageUpload";
	var $type_valid_args = array('authenticator', 'existing_entity',
		'head_items', 'obey_no_resize_flag', 'convert_to_image', 'require_crop', 'preselect_crop', 'crop_ratio');

	/**
	 * Set this flag to true to require images to be cropped before submitting; if Javascript is off, and 
	 * $crop_ratio != 0, uploaded images will be auto-cropped to the required ratio.
	 */
	var $require_crop = false;
	/**
	 * Set this flag to true to preselect a cropping region when the preview image is displayed
	 */
	var $preselect_crop = true;
	/**
	 * This setting defines the aspect ratio of the crop region. 0 is unrestricted, 1 is square, 2/3, 4/6 etc are valid.
	 */
	var $crop_ratio = 0;
	/**
	 * Set this flag to true to preserve full-sized uncropped images as well as full-sized cropped images
	 * (there's currently no interface for gaining access to these files)
	 */
	var $preserve_uncropped = false;
	
	function _upload_success($image_path, $image_url)
	{
		if (!empty($this->file) && !empty($this->file["original_path"])) {
			$orig_path = $this->file["original_path"];
		} else {
			$orig_path = $image_path;
		}

		if ($params = $this->_get_crop_params($orig_path))
		{
			if ($this->_crop_image($orig_path, $params))
			{
				if ($orig_path != $image_path) copy($orig_path,$image_path);
			}
		}
		parent::_upload_success($image_path, $image_url);
	}

	function get_head_items(&$head)
	{
		parent::get_head_items($head);
		$head->add_stylesheet(REASON_PACKAGE_HTTP_BASE_PATH . 'Jcrop/css/jquery.Jcrop.css');
		$head->add_javascript(REASON_PACKAGE_HTTP_BASE_PATH . 'Jcrop/js/jquery.Jcrop.min.js');
		$head->add_javascript(WEB_JAVASCRIPT_PATH.'image_crop.js');
	}

	function _get_hidden_display($current)
	{
		$fields['_reason_upload_crop_required'] = $this->require_crop;
		$fields['_reason_upload_crop_preselect'] = $this->preselect_crop;
		$fields['_reason_upload_crop_ratio'] = $this->crop_ratio;
		$fields['_reason_upload_crop_x'] = 0;
		$fields['_reason_upload_crop_y'] = 0;
		$fields['_reason_upload_crop_w'] = 0;
		$fields['_reason_upload_crop_h'] = 0;
		
		foreach ($fields as $field => $val)
			$html[] = '<input type="hidden" name="'.$field.'" value="'.$val.'" />';
		
		return parent::_get_hidden_display($current).join("\n",$html);
	}

	function _get_current_file_display($current)
	{
		// If we have an image in process, put its original dimensions out there for the cropper
		$display = parent::_get_current_file_display($current);
		$width = $height = 0;
		if ($current)
		{
			$info = getimagesize($current->original_path);
			if ($info)
			{
				list($width, $height) = $info;
			}
		}
		$display .= '<input type="hidden" name="_reason_upload_orig_h" value="'.$height.'" />';
		$display .= '<input type="hidden" name="_reason_upload_orig_w" value="'.$width.'" />';
		return $display;
	}

	function register_fields()
	{
		return array_merge(parent::register_fields(), array(
			'_reason_upload_orig_h',
			'_reason_upload_orig_w',
			'_reason_upload_crop_required',
			'_reason_upload_crop_preselect',
			'_reason_upload_crop_ratio',
			'_reason_upload_crop_x',
			'_reason_upload_crop_y',
			'_reason_upload_crop_h',
			'_reason_upload_crop_w'));
	}

	function _get_crop_params($image_path)
	{
		// Don't do anything without a valid image file
		if ($image = getimagesize($image_path))
		{
			list($image_w,$image_h) = $image;

			// First check the hidden fields on the form; if Javascript is active, those should be populated.
			$vars = $this->get_request();
			if ($vars['_reason_upload_crop_w'])
			{
				$w = round($vars['_reason_upload_crop_w']);
				$h = round($vars['_reason_upload_crop_h']);
				$x = round($vars['_reason_upload_crop_x']);
				$y = round($vars['_reason_upload_crop_y']);
			
				// Check for sane values from userland
				if ($w <= $image_w && $h <= $image_h && $x < $image_w && $y < $image_w)
				{
					return compact('w','h','x','y');
				}
			}
			
			// If we don't get cropping values from the form, we have to work out the appropriate values from the 
			// size of the image and the provided cropping ratio, but only if cropping is required.
			if ($this->crop_ratio && $this->require_crop)
			{
				if ($image_w/$image_h <= $this->crop_ratio)
				{
					$params = array(
						'w' => $image_w,
						'h' => round($image_w/$this->crop_ratio),
						'x' => 0,
						'y' => round($image_h/2 - ($image_w/$this->crop_ratio)/2)
						);
				} else {
					$params = array(
						'w' => round($image_h*$this->crop_ratio),
						'h' => $image_h,
						'x' => round($image_w/2 - ($image_h*$this->crop_ratio)/2),
						'y' => 0
						);
				}
				return $params;
			}
		}
	}
	
	function _crop_image($image_path, $params)
	{
		if ($this->preserve_uncropped) {
			// Preserve the unscaled image.
			
			$path_parts = pathinfo($image_path);
			if (isset($path_parts['extension']))
			{
				$ext = ".{$path_parts['extension']}";
				$ext_pattern = "/".preg_quote($ext, '/')."$/";
				$orig_path = preg_replace($ext_pattern, "-uncropped{$ext}",
					$image_path);
				if ($orig_path == $image_path) // in case the replace doesn't work
					$orig_path .= '.uncropped';
			} else {
				$orig_path = $image_path . '.uncropped';
			}
				
			if (copy($image_path, $orig_path)) {
				$this->original_path = $orig_path;
			}
		}
		
		if (!cut_image($params['w'], $params['h'], $params['x'], $params['y'], $image_path, $image_path)) return false;
	
		if ($this->file) {
			// file size will have (hopefully) changed after the resize
			$this->file["size"] = filesize($image_path);
		}
		return true;
	}	
}


/** @access private */
function _populate_flash_upload_head_items(&$head)
{
	if ($GLOBALS['_disco_upload_head_items_shown'])
		return;
	
    $scripts = array(
		REASON_PACKAGE_HTTP_BASE_PATH."plupload/plupload-2.1.4/js/plupload.full.min.js",
		REASON_FLASH_UPLOAD_URI . 'upload_support.js',
		REASON_HTTP_BASE_PATH."js/plupload_setup.js"
	);
	foreach ($scripts as $script) {
		if ($head && $head->get_num_times_markup_has_been_fetched() == 0) {
			// echo "INCLUDING [$script] with head_items technique!<P>";
			$head->add_javascript($script);
		} else {
			// echo "INCLUDING [$script] with inline technique!<P>";
			echo "<script src='$script'></script>";
		}
	}
	
	$GLOBALS['_disco_upload_head_items_shown'] = true;
}

/** @access private */
function _reason_upload_handle_entity(&$element, $expected_type,
	$filesystem_translator, $web_translator)
{
	if (!$element->existing_entity)
		return;
	
	if (is_numeric($element->existing_entity)) {
		$id = (int) $element->existing_entity;
		$entity = new entity($id);
	} else if (is_object($element->existing_entity)) {
		$entity = $element->existing_entity;
	} else {
		trigger_error("a $expected_type entity object or ID must be passed ".
			"as the existing entity to a Reason upload type; got ".
			var_export($element->existing_entity, true)." instead", WARNING);
		return;
	}
	
	if (!reason_is_entity($entity, $expected_type)) {
		trigger_error("an invalid existing entity was passed to a Reason ".
			"upload type", WARNING);
		return;
	}
	
	$element->existing_file = call_user_func($filesystem_translator, $entity);
	$element->existing_file_web = call_user_func($web_translator, $entity);
}

/** @access private */
function _get_disco_async_upload_session($authenticator)
{
	$sid = null;
	if (!empty($_REQUEST['_reason_upload_transfer_session'])) {
		$sid = $_REQUEST['_reason_upload_transfer_session'];
	} else if (!empty($_REQUEST['transfer_session'])) {
		$sid = $_REQUEST['transfer_session'];
	}
	
	if ($sid && reason_async_upload_session_exists($sid))
		return $sid;
	
	// We need to generate a new upload session if: none has yet been
	// created (obviously), or if the upload session was already sent in
	// a plasmature element's get_display() code (because if that's true and
	// we're in a plasmature init() method again, then this must be a different
	// Disco form than the one that created the existing upload session).
	$need_new_session = (!$GLOBALS['_disco_upload_session'] ||
		$GLOBALS['_disco_upload_session_sent']);
	
	if ($need_new_session) {
		$sid = reason_create_async_upload_session($authenticator);
		$GLOBALS['_disco_upload_session'] = $sid;
		$GLOBALS['_disco_upload_session_sent'] = false;
		// echo "GENERATED NEW SID ($sid)<BR>";
	} else {
		$sid = $GLOBALS['_disco_upload_session'];
		// echo "GETTING SID FROM DISCO_UPLOAD_SESSION ($sid)<BR>";
	}
	
	return $sid;
}

/** @access private */
function _get_disco_async_upload_internal_field_names()
{
	static $fields = array('user_session', 'transfer_session', 'receiver',
		'remover', 'user_id');
	$decorated_fields = array();
	foreach ($fields as $name)
		$decorated_fields[] = "_reason_upload_$name";
	return $decorated_fields;
}

/** @access private */
function _get_disco_async_upload_hidden_fields($upload_sid)
{
	if ($GLOBALS['_disco_upload_session_sent'])
		return '';
		
	$session =& get_reason_session();
	
	$user_id = isset($_REQUEST['user_id']) ? $_REQUEST['user_id'] : 0;
		
	// IMPORTANT NOTE: Keep this list of fields in sync with the list in
	// _get_disco_async_upload_internal_field_names() above.
	$fields = array(
		'user_session' => $session->get_id(),
		'transfer_session' => $upload_sid,
		'receiver' => reason_get_async_upload_script_uri('receive'),
		'remover' => reason_get_async_upload_script_uri('destroy'),
		'user_id' => turn_into_int($user_id)
	);
	
	$html = array();
	foreach ($fields as $name => $value) {
		$html[] = '<input type="hidden" name="_reason_upload_'.$name.'" '.
			'value="'.$value.'" />';
	}
	
	$GLOBALS['_disco_upload_session_sent'] = true;
	return implode("\n", $html);
}

/** @access private */
function _ensure_async_upload_head_items_shown()
{
	if ($GLOBALS['_disco_upload_head_items_shown'])
		return;
	
	$head_items = new HeadItems();
	_populate_flash_upload_head_items($head_items);
	return $head_items->get_head_item_markup();
}

/** @access private */
function _reason_get_disco_uploaded_file($name, $async_sid,
	$want_original=false)
{
	$upload = reason_get_uploaded_file($name, $async_sid);
	// echo "upload:<BR><PRE>"; var_dump($upload); echo "</PRE>";
	if (!$upload || !$upload->get_filename())
		return null;
	
	$path = null;
	if ($want_original)
		$path = $upload->get_original_path();
	if (!$path)
		$path = $upload->get_temporary_path();
	
	return array(
		"name" => $upload->get_filename(),
		"path" => $path,
		"tmp_name" => $path, // former name
		"original_path" => $upload->get_original_path(),
		"modified_path" => $upload->get_temporary_path(),
		"size" => filesize($path),
		"type" => $upload->get_mime_type("application/octet-stream")
	);
}

function _embed_plupload_stylesheet($head_items = null)
{
	// echo "EMBEDDING STYLESHEET WITH [" . ($head_items ? "head_items" : "inline") . "] technique...<P>";
	if ($head_items && $head_items->get_num_times_markup_has_been_fetched() == 0) {
		// echo "<script>console.log('embedding stylesheet with head_items...');</script>";
		$head_items->add_stylesheet(REASON_PACKAGE_HTTP_BASE_PATH . 'plupload/css/reason_plupload.css');
	} else {
		// echo "<script>console.log('embedding stylesheet inline...');</script>";
		echo "<link rel='stylesheet' type='text/css' href='" . REASON_PACKAGE_HTTP_BASE_PATH . "plupload/css/reason_plupload.css'>";
	}
}

function _get_plupload_js_setup_snippet($upload_sid, $element_name)
{
	// $js = "<script type=\"text/javascript\">var pluploadSubmissionUrl = '" . REASON_HTTP_BASE_PATH . "scripts/upload/receive.php?user_id=0&upload_sid=" . $upload_sid . "';</script>";
	// $js .= "<script type=\"text/javascript\">var pluploadFieldName = '" . $element_name . "'; console.log('setting pluploadFieldName to " . $element_name . "...');</script>";

	// can i use the same submissionUrl for different elements? Not sure about that yet...

	// var pluploadConfig = {submissionUrl: "{$REASON_HTTP_BASE_PATH}scripts/upload/receive.php?user_id=0&upload_sid={$upload_sid}", fieldNames: []};
	$submitUrl = REASON_HTTP_BASE_PATH . "scripts/upload/receive.php?user_id=0&upload_sid=" . $upload_sid;
	$destructionUrl = REASON_HTTP_BASE_PATH . "scripts/upload/destroy.php?upload_sid=" . $upload_sid;

	$js = <<<JAVASCRIPT
		<script type="text/javascript">
			if (!window.console) { console = {log: function() {}}; }
			console.log("creating pluploadConfig inline...[{$upload_sid}], [{$element_name}]");
			if (!pluploadConfig) {
				var pluploadConfig = [];
			}
			// pluploadConfig.fieldNames.push("{$element_name}");
			pluploadConfig.push({submissionUrl: "{$submitUrl}", destructionUrl: "{$destructionUrl}", fieldName: "{$element_name}"});

		</script>
JAVASCRIPT;
	// $js = "CREATING PLUPLOADCONFIG<P>" . $js;
	return $js;
}

function _get_plupload_dom_stubs($can_add_file, $current, $element_name, $add_text=null, $replace_text=null) {
	if (!$can_add_file)
		return '';

	$label = null;
	if (!$current && $add_text) {
		$label = $add_text;
	} else if ($current) {
		$label = ($replace_text)
			? $replace_text
			: "Replace saved file:";
	}

	$uploadEl = "";

	$uploadEl .= "<div id='upload_filelist_" . $element_name . "' style='display:none'></div>";

	$uploadEl .= "<div id='file_upload_" . $element_name . "'>";

	if ($label) {
		$uploadEl .= '<span class="smallText">'.$label."</span><br />";
	}
	// $uploadEl .= "<a id='upload_browse_" . $element_name . "' href='javascript:;'>[Browse...]</a>";
	$uploadEl .= "<div class='plupload_dropzone' id='upload_browse_" . $element_name . "'><span class='default_text'>Click to add file, or drag/drop onto this zone...</span></div>";

	$uploadEl .= "<pre class='plupload_error_console' id='upload_console_" . $element_name . "'></pre>";
	$uploadEl .= "</div>";

	return $uploadEl;
}
