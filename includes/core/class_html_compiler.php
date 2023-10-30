<?php
class HTML_Compiler extends HTML {

	// VARS

    private $current_file = NULL;
    private $current_line_no = 1;
    private $literal_blocks = [];
    private $permitted_tokens = ['true','false','yes','no','on','off','null'];
	
	// REGEXP
	
    private $db_qstr_regexp = NULL;
    private $si_qstr_regexp = NULL;
    private $qstr_regexp = NULL;
    private $func_regexp = NULL;
    private $var_bracket_regexp = NULL;
    private $dvar_guts_regexp = NULL;
    private $dvar_regexp = NULL;
    private $svar_regexp = NULL;
    private $avar_regexp = NULL;
    private $var_regexp = NULL;
	
	// CONSTRUCT

    public function __construct() {
        $this->db_qstr_regexp = '"[^"\\\\]*(?:\\\\.[^"\\\\]*)*"';
        $this->si_qstr_regexp = '\'[^\'\\\\]*(?:\\\\.[^\'\\\\]*)*\'';
        $this->qstr_regexp = '(?:'.$this->db_qstr_regexp.'|'.$this->si_qstr_regexp.')';
        $this->var_bracket_regexp = '\[\$?[\w.]+]';
        $this->dvar_guts_regexp = '\w+(?:'.$this->var_bracket_regexp.')*(?:\.\$?\w+(?:'.$this->var_bracket_regexp.')*)*';
        $this->dvar_regexp = '\$'.$this->dvar_guts_regexp;
        $this->svar_regexp = '\%\w+\.\w+\%';
        $this->avar_regexp = '(?:'.$this->dvar_regexp.'|'.$this->svar_regexp.')';
        $this->var_regexp = '(?:'.$this->avar_regexp.'|'.$this->qstr_regexp.')';
        $this->func_regexp = '[a-zA-Z_]\w*';
    }
	
	// GENERAL

    public function compile_file($file, $source_content, &$compiled_content) {
		// vars
        $this->current_file = $file;
        $this->current_line_no = 1;
        $ldq = preg_quote('{', '!');
        $rdq = preg_quote('}', '!');
        // literals
        preg_match_all("!{$ldq}\s*literal\s*{$rdq}(.*?){$ldq}\s*/literal\s*{$rdq}!s", $source_content, $match);
        $this->literal_blocks = $match[1];
        $source_content = preg_replace("!{$ldq}\s*literal\s*{$rdq}(.*?){$ldq}\s*/literal\s*{$rdq}!s", self::quote_replace('{literal}'), $source_content);
        // parse
        preg_match_all("!{$ldq}\s*(.*?)\s*{$rdq}!s", $source_content, $match);
        $template_tags = $match[1];
        $text_blocks = preg_split("!{$ldq}.*?{$rdq}!s", $source_content);
        // compile
        $compiled_tags = [];
        for ($i = 0, $max = count($template_tags); $i < $max; $i++) {
            $this->current_line_no += substr_count($text_blocks[$i], "\n");
            $compiled_tags[] = $this->compile_tag($template_tags[$i]);
            $this->current_line_no += substr_count($template_tags[$i], "\n");
        }
		// combine
        $compiled_content = '';
        for ($i = 0, $max = count($compiled_tags); $i < $max; $i++) {
            if (!$compiled_tags[$i]) $text_blocks[$i+1] = preg_replace('!^(\r\n|\r|\n)!', '', $text_blocks[$i+1]);
            $compiled_content .= $text_blocks[$i].$compiled_tags[$i];
        }
        $compiled_content .= $text_blocks[$i];
		// file end
        if (($_len = strlen($compiled_content)) && ($compiled_content[$_len - 1] == "\n" )) {
            $compiled_content = substr($compiled_content, 0, -1);
        }
		// output
        return true;
    }

    private function compile_tag($tag) {
		// vars
        preg_match('/^(?:('.$this->var_regexp.'|\/?'.$this->func_regexp.'))(?:\s+(.*))?$/xs', $tag, $match);
        $command = $match[1];
        $args = $match[2] ?? null;
		// type
        if (preg_match('!^'.$this->var_regexp.'$!', $command)) return "<?php echo ".$this->parse_var_props($command)."; ?>\n";
		else if (preg_match('/^LANG_[a-z0-9_]+$/i', $command)) return "<?php echo self::\$_langs['".$command."']; ?>\n";
		else if ($command == 'include') return $this->compile_include_tag($args);
		else if ($command == 'if') return $this->compile_if_tag($args);
		else if ($command == 'else') return '<?php else: ?>';
		else if ($command == 'elseif') return $this->compile_if_tag($args, true);
		else if ($command == '/if') return '<?php endif; ?>';
		else if ($command == 'section') return $this->compile_section($args);
		else if ($command == '/section') return "<?php endfor; endif; ?>";
		else if ($command == 'literal') return $this->compile_literal();
		else $this->syntax_error("wrong tag '".$command."'", E_USER_ERROR, __FILE__, __LINE__);
    }

    private function compile_minus($args) {
        return $args[0] - $args[1];
    }
	
	// PARSE

    private function parse_vars_props(&$tokens) {
        foreach ($tokens as $key => $val) $tokens[$key] = $this->parse_var_props($val);
    }

    private function parse_var_props($val) {
        $val = trim($val);
        if (preg_match('!^('.$this->dvar_regexp.')$!', $val, $match)) {
			return $this->parse_var($match[1]);
		} else if (preg_match('!^'.$this->db_qstr_regexp.'$!', $val)) {
			preg_match('!^('.$this->db_qstr_regexp.')$!', $val, $match);
			return $this->expand_quoted_text($match[1]);
		} else if (preg_match('!^'.$this->si_qstr_regexp.'$!', $val)) {
			return $val;
		} elseif(!in_array($val, $this->permitted_tokens) && !is_numeric($val)) {
			return $this->expand_quoted_text('"'.$val.'"');
		}
    }

    private function parse_var($var_expr) {
		// handling
		$var_ref = is_numeric($var_expr[0]) ? $var_expr : substr($var_expr, 1);
		preg_match_all('!(?:^\w+)|(?:'.$this->var_bracket_regexp.')|\.\$?\w+|\S+!', $var_ref, $match);
		$indexes = $match[0];
		$var_name = array_shift($indexes);
		$output = ($var_name == 'ref') ? $this->compile_ref($indexes) : "self::\$_tpl_vars['$var_name']";
		// inner vars
		foreach ($indexes as $index) {
			if ($index[0] == '[') {
				$index = substr($index, 1, -1);
				if (is_numeric($index)) {
					$output .= "[$index]";
				} elseif ($index[0] == '$') {
					if (strpos($index, '.') !== false) $output .= '['.$this->parse_var($index).']';
					else $output .= "[self::\$_tpl_vars['".substr($index, 1)."']]";
				} else {
					$var_parts = explode('.', $index);
					$var_section = $var_parts[0];
					$var_section_prop = $var_parts[1] ?? 'index';
					$output .= "[self::\$_sections['$var_section']['$var_section_prop']]";
				}
			} else if ($index[0] == '.') {
				if ($index[1] == '$') $output .= "[self::\$_tpl_vars['".substr($index, 2)."']]";
				else $output .= "['".substr($index, 1)."']";
			} else {
				$output .= $index;
			}
		}
		// output
        return $output;
    }
	
    private function parse_attrs($args) {
        // tokenize
        preg_match_all('/(?:'.$this->qstr_regexp.' | (?>[^"\'=\s]+))+ |[=]/x', $args, $match);
        $tokens = $match[0];
		// vars
        $attrs = [];
        $state = 0;
		// parse
        foreach ($tokens as $token) {
            switch ($state) {
                case 0:
                    if (preg_match('!^\w+$!', $token)) {
                        $attr_name = $token;
                        $state = 1;
                    } else $this->syntax_error("invalid attribute name: '".$token."'", E_USER_ERROR, __FILE__, __LINE__);
                    break;
                case 1:
                    if ($token == '=') {
                        $state = 2;
                    } else $this->syntax_error("expecting '=' after attribute name '".$token."'", E_USER_ERROR, __FILE__, __LINE__);
                    break;
                case 2:
                    if ($token != '=') {
                        if (preg_match('!^(on|yes|true)$!', $token)) $token = 'true';
						else if (preg_match('!^(off|no|false)$!', $token)) $token = 'false';
						else if ($token == 'null') $token = 'null';
						else if (!preg_match('!^'.$this->var_regexp.'$!', $token)) $token = '"'.addslashes($token).'"';
                        $attrs[$attr_name] = $token;
                        $state = 0;
                    } else $this->syntax_error("'=' cannot be an attribute value", E_USER_ERROR, __FILE__, __LINE__);
                    break;
            }
            $last_token = $token;
        }
		// validate
        if($state != 0) {
            if ($state == 1) $this->syntax_error("expecting '=' after attribute name '".$last_token."'", E_USER_ERROR, __FILE__, __LINE__);
			else $this->syntax_error("missing attribute value", E_USER_ERROR, __FILE__, __LINE__);
        }
		// output
        $this->parse_vars_props($attrs);
        return $attrs;
    }
	
	// COMPILE

    private function compile_include_tag($args) {
		// vars
        $attrs = $this->parse_attrs($args);
        $arg_list = [];
        if (empty($attrs['file'])) $this->syntax_error("missing 'file' attribute in include tag", E_USER_ERROR, __FILE__, __LINE__);
		// attrs
        foreach ($attrs as $arg_name => $arg_value) {
            if ($arg_name == 'file') { $include_file = $arg_value; continue; }
            if (is_bool($arg_value)) $arg_value = $arg_value ? 'true' : 'false';
            $arg_list[] = "'$arg_name' => $arg_value";
        }
		// output
        $output = "<?php ";
        $output .= "\$_html_tpl_vars = self::\$_tpl_vars;\n";
        $output .= "self::include_file(['file'=>".$include_file.", 'vars'=>[".implode(',', $arg_list)."]]);\n";
        $output .= "self::\$_tpl_vars = \$_html_tpl_vars;\n";
        $output .= "unset(\$_html_tpl_vars);\n";
        $output .= " ?>";
		// return
        return $output;
    }
	
    private function compile_if_tag($args, $elseif = false) {
        // tokenize
        preg_match_all('/(?>'.$this->var_regexp.'|\-?0[xX][0-9a-fA-F]+|\-?\d+(?:\.\d+)?|\.\d+|!==|===|==|!=|<>|<=|>=|\&\&|\|\||\(|\)|,|\!|\^|=|\&|\~|<|>|\||\%|\+|\-|\/|\*|\b\w+\b|\S+)/x', $args, $match);
        $tokens = $match[0];
        // parenthesis
        $token_count = array_count_values($tokens);
        if (isset($token_count['(']) && $token_count['('] != $token_count[')']) $this->syntax_error('unbalanced parenthesis in if statement', E_USER_ERROR, __FILE__, __LINE__);
		// tokens
        for ($i = 0, $max = count($tokens); $i < $max; $i++) {
            $token = &$tokens[$i];
			if (is_numeric($token) || in_array($token, ['!','%','!==','==','===','>','<','!=','<>','<=','>=','&&','||','|','^','&','~','(',')',',','+','-','*','/']) || preg_match('!^'.$this->func_regexp.'$!', $token)) continue;
			else if (preg_match('!^'.$this->var_regexp.'$!', $token)) $token = $this->parse_var_props($token);
			else $this->syntax_error("unidentified token '".$token."'", E_USER_ERROR, __FILE__, __LINE__);
        }
		// output
        return $elseif ? '<?php elseif ('.implode(' ', $tokens).'): ?>' : '<?php if ('.implode(' ', $tokens).'): ?>';
    }
	
    private function compile_section($args) {
		// vars	
        $attrs = $this->parse_attrs($args);
        $section_name = $attrs['name'];
        if (empty($section_name)) $this->syntax_error("missing section name", E_USER_ERROR, __FILE__, __LINE__);
		// parse		
        $output = '<?php ';
        $output .= "if (isset(self::\$_sections[$section_name])) unset(self::\$_sections[$section_name]);\n";
        $section_props = "self::\$_sections[$section_name]";
        foreach ($attrs as $attr_name => $attr_value) {
			if ($attr_name == 'loop') $output .= "{$section_props}['loop'] = is_array(\$_loop=$attr_value) ? count(\$_loop) : max(0, (int)\$_loop); unset(\$_loop);\n";
			else if ($attr_name == 'show') {
				if (is_bool($attr_value)) $show_attr_value = $attr_value ? 'true' : 'false';
				else $show_attr_value = "(bool)$attr_value";
				$output .= "{$section_props}['show'] = $show_attr_value;\n";
			}
			else if ($attr_name == 'name') $output .= "{$section_props}['$attr_name'] = $attr_value;\n";
			else if ($attr_name == 'max' || $attr_name == 'start') $output .= "{$section_props}['$attr_name'] = (int)$attr_value;\n";
			else if ($attr_name == 'step') $output .= "{$section_props}['$attr_name'] = ((int)$attr_value) == 0 ? 1 : (int)$attr_value;\n";
			else $this->syntax_error("unknown section attribute - '".$attr_name."'", E_USER_ERROR, __FILE__, __LINE__);
        }
        if (!isset($attrs['show']))
            $output .= "{$section_props}['show'] = true;\n";
        if (!isset($attrs['loop']))
            $output .= "{$section_props}['loop'] = 1;\n";
        if (!isset($attrs['max']))
            $output .= "{$section_props}['max'] = {$section_props}['loop'];\n";
        else
            $output .= "if ({$section_props}['max'] < 0)\n" .
                       "    {$section_props}['max'] = {$section_props}['loop'];\n";
        if (!isset($attrs['step']))
            $output .= "{$section_props}['step'] = 1;\n";
        if (!isset($attrs['start']))
            $output .= "{$section_props}['start'] = {$section_props}['step'] > 0 ? 0 : {$section_props}['loop']-1;\n";
        else {
            $output .= "if ({$section_props}['start'] < 0)\n" .
                       "    {$section_props}['start'] = max({$section_props}['step'] > 0 ? 0 : -1, {$section_props}['loop'] + {$section_props}['start']);\n" .
                       "else\n" .
                       "    {$section_props}['start'] = min({$section_props}['start'], {$section_props}['step'] > 0 ? {$section_props}['loop'] : {$section_props}['loop']-1);\n";
        }
        $output .= "if ({$section_props}['show']) {\n";
        if (!isset($attrs['start']) && !isset($attrs['step']) && !isset($attrs['max'])) {
            $output .= "    {$section_props}['total'] = {$section_props}['loop'];\n";
        } else {
            $output .= "    {$section_props}['total'] = min(ceil(({$section_props}['step'] > 0 ? {$section_props}['loop'] - {$section_props}['start'] : {$section_props}['start']+1)/abs({$section_props}['step'])), {$section_props}['max']);\n";
        }
        $output .= "    if ({$section_props}['total'] == 0)\n" .
                   "        {$section_props}['show'] = false;\n" .
                   "} else\n" .
                   "    {$section_props}['total'] = 0;\n";
        $output .= "if ({$section_props}['show']):\n";
        $output .= "
            for ({$section_props}['index'] = {$section_props}['start'], {$section_props}['iteration'] = 1;
                 {$section_props}['iteration'] <= {$section_props}['total'];
                 {$section_props}['index'] += {$section_props}['step'], {$section_props}['iteration']++):\n";
        $output .= "?>";
		// output
        return $output;
    }
	
    private function compile_ref(&$indexes) {
		// vars
        $ref = substr($indexes[0], 1);
        foreach($indexes as $index_no => $index) {
            if (substr($index, 0, 1) != '.' && $index_no < 2 || !preg_match('!^(\.|\[)!', $index)) $this->syntax_error('$ref'.implode('', array_slice($indexes, 0, 2)).' is an invalid reference', E_USER_ERROR, __FILE__, __LINE__);
        }
		// search
		if ($ref == 'section') {
			array_shift($indexes);
			$var = $this->parse_var_props(substr($indexes[0], 1));
			$compiled_ref = "self::\$_sections[$var]";
		} else if ($ref == 'const') {
			array_shift($indexes);
			$val = $this->parse_var_props(substr($indexes[0],1));
			$compiled_ref = '@constant('.$val.')';
			$max_index = 1;
		} else $this->syntax_error('$ref.'.$ref.' is an unknown reference', E_USER_ERROR, __FILE__, __LINE__);
		// validate
        if (isset($max_index) && count($indexes) > $max_index) $this->syntax_error('$ref'.implode('', $indexes).' is an invalid reference', E_USER_ERROR, __FILE__, __LINE__);
		// output
        array_shift($indexes);
        return $compiled_ref;
    }
	
	private function compile_literal() {
		//list (,$literal_block) = each($this->literal_blocks);
		$literal_block = current($this->literal_blocks);
		next($this->literal_blocks);
		$this->current_line_no += substr_count($literal_block, "\n");
		return "<?php echo '".str_replace("'", "\'", str_replace("\\", "\\\\", $literal_block))."'; ?>\n";
	}
	
	// SERVICE

    private function syntax_error($msg, $type = E_USER_ERROR, $file = NULL, $line = NULL) {
        $info = (isset($file) && isset($line)) ? ' ('.basename($file).', line '.$line.')' : NULL;
        trigger_error('HTML: [in '.$this->current_file.' line '.$this->current_line_no.']: syntax error: '.$msg.$info, $type);
    }
	
    private function expand_quoted_text($var_expr) {
        if (preg_match_all('%(?:\`(?<!\\\\)\$'.$this->dvar_guts_regexp.'\`)|(?:(?<!\\\\)\$\w+(\[[a-zA-Z0-9]+\])*)%', $var_expr, $match)) {
            $match = $match[0];
            $replace = [];
            foreach ($match as $var) $replace[$var] = '".('.$this->parse_var(str_replace('`','',$var)).')."';
            $var_expr = strtr($var_expr, $replace);
            $return = preg_replace('~\.""|(?<!\\\\)""\.~', '', $var_expr);
        } else {
            $return = $var_expr;
        }
        return preg_replace('~^"([\s\w]+)"$~', "'\\1'", $return);
    }
	
    private function quote_replace($str) {
        return preg_replace('![\\$]\d!', '\\\\\\0', $str);
    }

}

?>