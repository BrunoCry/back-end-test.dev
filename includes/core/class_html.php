<?php

class HTML {
	
	// VARS

    public static $template_dir = 'partials';
    public static $compile_dir = 'partials_c';
    public static $compile_check = true;
    public static $force_compile = false;
    public static $_langs = [];
    public static $_sections = [];
    public static $_tpl_vars = [];

	// GENERAL

    public static function assign($tpl_var, $value = null) {
        if (is_array($tpl_var)){
            foreach ($tpl_var as $key => $val) {
                if ($key != '') self::$_tpl_vars[$key] = $val;
            }
        } else {
            if ($tpl_var != '') self::$_tpl_vars[$tpl_var] = $value;
        }
    }

    public static function display($file) {		
        self::fetch($file, true);
    }

    public static function fetch($file, $display = false) {
        $path = self::get_path($file);
        if ($display) {
            if (self::is_compiled($file, $path) || self::compile($file, $path)) require($path);
        } else {
            ob_start();
            if (self::is_compiled($file, $path) || self::compile($file, $path)) require($path);
            $results = ob_get_contents();
            ob_end_clean();
			return $results;
        }
    }
	
	public static function main_content($file, $mode = 0) {
		if ($mode == 1) return self::fetch($file);
		else self::assign('main_content', $file);
		return false;
	}

	public static function add_lang($id, $path) {
		$langs = require($path);
		foreach ($langs as $key => $value) self::$_langs[$key] = $value[$id];
	}
	
	public static function lang($key) {
		return self::$_langs[$key];
	}
	
	// PRIVATE

    private static function is_compiled($file, $path) {
        if (!self::$force_compile && file_exists($path)) {
            if (!self::$compile_check) {
                return true;
            } else {
                $params = ['resource_name'=>$file, 'get_source'=>false];
                if (!self::fetch_resource_info($params)) return false;
                return ($params['resource_timestamp'] <= filemtime($path)) ? true : false;
            }
        } else return false;
    }

    private static function compile($file, $path) {
        $params = ['resource_name'=>$file];
        if (!self::fetch_resource_info($params)) return false;
        $source_content = $params['source_content'];
        $resource_timestamp = $params['resource_timestamp'];
        if (self::compile_source($file, $source_content, $compiled_content)) {
            $params = ['compile_path'=>$path, 'compiled_content'=>$compiled_content, 'resource_timestamp'=>$resource_timestamp];
            self::write_compiled_resource($params);
            return true;
        } else {
            return false;
        }

    }

    private static function compile_source($file, &$source_content, &$compiled_content) {
        $compiler = new HTML_Compiler;
        return $compiler->compile_file($file, $source_content, $compiled_content);
    }

    private static function get_path($file) {
		$return = self::$compile_dir.DIRECTORY_SEPARATOR;
        if (isset($file)) {
            $filename = urlencode(basename($file));
            $crc32 = crc32($file).'^';
            $crc32 = '%'.substr($crc32, 0, 3).'^%'.$crc32;
            $return .= $crc32.$filename;
        }
        return $return.'.php';
    }

    private static function fetch_resource_info(&$params) {
        if (!isset($params['get_source'])) $params['get_source'] = true;
        $return = false;
        $_params = ['resource_name'=>$params['resource_name']];
        if (self::parse_resource_name($_params)) {
            $resource_name = $_params['resource_name'];
			if ($params['get_source']) $params['source_content'] = self::read_file($resource_name);
			$params['resource_timestamp'] = filemtime($resource_name);
			$return = is_file($resource_name);
        }
        if (!$return) trigger_error('unable to read resource: "'.$params['resource_name'].'"');
        return $return;
    }

    private static function parse_resource_name(&$params) {
		$paths = [self::$template_dir, '.'];
		foreach ($paths as $path) {
			$full_path = $path.DIRECTORY_SEPARATOR.$params['resource_name'];
			if (file_exists($full_path) && is_file($full_path)) {
				$params['resource_name'] = $full_path;
				return true;
			}
		}
		return false;
    }

	private static function read_file($filename) {
		if (file_exists($filename) && ($fd = @fopen($filename, 'rb'))) {
			$contents = '';
			while (!feof($fd)) $contents .= fread($fd, 8192);
			fclose($fd);
			return $contents;
		} else
			return false;
	}

    private static function include_file($params) {
        self::$_tpl_vars = array_merge(self::$_tpl_vars, $params['vars']);
        $path = self::get_path($params['file']);
        if (self::is_compiled($params['file'], $path) || self::compile($params['file'], $path)) require($path);
    }
	
	private static function write_compiled_resource($params) {
		if (!@is_writable(self::$compile_dir)) {
			if (!@is_dir(self::$compile_dir)) {
				trigger_error('the compile_dir \''.self::$compile_dir.'\' does not exist, or is not a directory.', E_USER_ERROR);
				return false;
			}
			trigger_error('unable to write to compile_dir \''.realpath(self::$compile_dir).'\'. Be sure compile_dir is writable by the web server user.', E_USER_ERROR);
			return false;
		}
		$_params = ['filename' => $params['compile_path'], 'contents' => $params['compiled_content']];
		self::write_file($_params);
		touch($params['compile_path'], $params['resource_timestamp']);
		return true;
	}

	private static function write_file($params) {
		$dirname = dirname($params['filename']);
		$tmp_file = $dirname.DIRECTORY_SEPARATOR.uniqid('');
		if (!($fd = @fopen($tmp_file, 'w'))) { trigger_error("problem writing temporary file '".$tmp_file."'"); return false; }
		fwrite($fd, $params['contents']);
		fclose($fd);
		if (file_exists($params['filename'])) @unlink($params['filename']);
		@rename($tmp_file, $params['filename']);
		@chmod($params['filename'], 0644);
		return true;
	}

}
?>