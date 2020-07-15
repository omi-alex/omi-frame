<?php

/**
 * Description of QPHPTokenFile
 *
 * @author Alex
 */
class QPHPTokenFile extends QPHPToken
{
	protected static $Obj_To_Code_Props = ["children" => null, 'docComment' => null, 
											'filename' => null, 'is_patch' => false, 'extension' => null, 'fileParts' => null, 
											'expand_in_methods' => null, 'pullDocComment' => null];
	
	/**
	 * File name
	 *
	 * @var string
	 */
	public $filename;
	/**
	 * True if this is a patch
	 *
	 * @var boolean 
	 */
	public $is_patch = false;
	/**
	 *
	 * @var string
	 */
	public $extension;
	/**
	 *
	 * @var string[]
	 */
	public $fileParts;
	
	public static $Cache = array();

	/**
	 * Constructor
	 * 
	 * @param string $filename
	 */
	public function __construct($filename = null)
	{
		parent::__construct(null);
		
		$this->filename = realpath($filename);
		$this->extension = pathinfo($this->filename, PATHINFO_EXTENSION);
		$this->fileParts = explode(".", pathinfo($this->filename, PATHINFO_BASENAME));
		
		// var_dump("QPHPTokenFile: {$this->filename}");
	}

	public function beginParse($expand_in_methods = true, $saved_tokens = null, $expand_arrays = false, $saved_tokens_needs_expand = false)
	{
		/*
		 *  An array of token identifiers. Each individual token identifier is either a single character (i.e.: ;, ., >, !, etc...), 
		 *  or a three element array containing the token index in element 0, the string content of the original token in element 1 
		 *  and the line number in element 2. 
		 */
		
		// var_dump($tokens);
		
		// var_dump("====================================================================", $this->filename);
		
		try
		{
			$file_info = new QGeneratePatchInfo($this->filename);

			/*
			$modif_date = filemtime($this->filename);
			if (($cache_data = self::$Cache[$this->filename]) && (self::$Cache[$this->filename][0] === $modif_date))
			{
				list(,$tokens, $expand_output, $is_patch) = $cache_data;
			}
			else
			{
			 * 
			 */
			$saved_tokens = null;
			// $ext = $file_info->type;
			$expand_output = $file_info->expandOutput();

			// var_dump("Parsephpfile: {$this->filename} :: ".($saved_tokens ? "from cache" : ""));
			// var_dump($expand_output && $saved_tokens_needs_expand);

			$is_patch = false;

			$contents = file_get_contents($this->filename);
			# fix broken files (missing UTF8 header but utf8)
			$file_encoding = mb_detect_encoding($contents, 'UTF-8, ISO-8859-1', true);
			if ($file_encoding === false)
				throw new \Exception('Unable to detect encoding for file: '.$this->filename);
			$contents = mb_convert_encoding($contents, 'UTF-8', $file_encoding);
			
			if ($file_info->type === "tpl")
				$contents = self::ParseTemplateMarkings($contents, $this->filename);

			if ($expand_output && $saved_tokens && $saved_tokens_needs_expand)
			{
				$tokens = $this->getAllTokens($contents, $expand_output, $is_patch, $saved_tokens);
			}
			else
			{
				$tokens = $saved_tokens ?: $this->getAllTokens($contents, $expand_output, $is_patch, null);
			}
			
			// $this->tokensCache = $tokens;
			$this->expand_in_methods = $expand_in_methods;
				/*
				self::$Cache[$this->filename] = array(filemtime($this->filename), $tokens, $expand_output, $is_patch);
			}
			 */
			// var_dump($this->filename, $expand_output);

			// we know at this point if it's a patch
			$this->is_patch = $is_patch;

			$pos = 0;
			$this->parse($tokens, $tokens[0], $pos, $expand_output, $expand_in_methods, $expand_arrays);
		}
		catch (Exception $ex)
		{
			var_dump($this->filename);
			throw $ex;
		}
	}
	
	public function getClone()
	{
		$clone = new QPHPTokenFile($this->filename);
		$clone->beginParse($this->expand_in_methods, $this->tokensCache);
		return $clone;
	}
	
	public function parse($tokens, $tok, &$pos, $expand_output = false, $expand_in_methods = true, $expand_arrays = false)
	{
		// ParseOutput($parent, $tokens, $tok = null, &$pos = 0, $expand_output = false, $stacked = false, $expand_in_methods = true, $expand_arrays = false)
		self::ParseOutput($this, $tokens, $tok, $pos, $expand_output, false, $expand_in_methods, $expand_arrays);
		
		/*if ($expand_output)
			echo "<textarea>{$this}</textarea>";*/
	}

	public function getAppendPos()
	{
		return ($this->children) ? count($this->children) : 0;
	}

	public function getPrependPos()
	{
		return 0;
	}
}

