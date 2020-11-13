<?php

trait QCodeSync2_Gen_Urls
{
	public function resyncUrlController(QCodeSyncNode $element, $tag = "")
	{
		try
		{
			$__t0 = microtime(true);

			$debug_log = "";

			$sync_item = $element->data["url"][$tag];
			$full_path = $element->getUrlPath();

			$obj_parsed = $element->getTokens("url", $tag); // QPHPToken::ParsePHPFile($full_path, true);
			if (!$obj_parsed)
				throw new Exception("Unable to parse `{$full_path}` in QCodeSync::resyncUrlController");

			if ($this->debugMode)
				echo "Processing URL: {$full_path}<br/>\n";

			$render_code = $this->managePatching($sync_item, $obj_parsed, null, false, $debug_log);
			if (!$render_code)
			{
				$render_code = $obj_parsed;
			}

			$gen_info = new QGeneratePatchInfo($full_path);

			if ($element->namespace)
				$gen_info->namespace = $element->namespace;

			// echo "<textarea>{$render_code}</textarea>";
			if (isset($sync_item->patch))
			{
				$gen_info->__tpl_parent = $sync_item->patch;
				$gen_info->__tpl_mode = $sync_item->mode;
				$gen_info->__tpl_tag = $sync_item->tag;
			}

			$this->DebugGenerate($gen_info, $render_code);
			$render_code->generate($gen_info);
			//echo "<textarea>{$render_code}</textarea>";

			$url_controller = $render_code->generateUrlController($gen_info);
			if (!$url_controller)
				throw new Exception("Unable to generate controller for: ".$element->getUrlPath());
			$code = QPHPToken::ParsePHPString($url_controller->toString(), false, false);
			// we need to rebuild $code as it's not parsed ok

			if ($gen_info->class_name === "index")
			{
				if ($this->debugMode)
					var_dump("to do");
				return;
			}
			else
			{
				if (!file_exists($element->classPath))
					$this->newFiles[$element->watchFolder][] = $element;

				// echo "<textarea>{$code}</textarea>";
				// echo "<textarea>{$element->classPath}</textarea>";
				// $this->mergeWithFileClass($code, $element->classPath, $full_path, true);
				$final_class = $element->getFinalTokens();
				$element->requiresCompiled = true;

				// $this->classPaths[$first_class->className] = $full_path;
				// $patch_class->transformToTrait($first_class->className."_GenTrait", $first_class, true);

				if ($final_class)
				{
					$final_class_obj = $final_class->findFirst(".QPHPTokenClass");
					$final_class_obj->mergeMethods($code->findFirst(".QPHPTokenClass")->methods);
					// echo "<textarea>{$final_class_obj}</textarea>";
					if ($final_class_obj->type === "class")
						$final_class_obj->mergeImplements("\\QIUrlController");
					// echo "<textarea>{$final_class_obj}</textarea>";
				}
				else
				{
					// $element->setFinalToken($code);
					throw new Exception("getFinalTokens should now always return");
				}

				$this->writtenFiles[$element->watchFolder][] = $element;
			}

			/*
			if ($sync_item->class_merge_node)
			{
				throw new Exception("are we using this ?");
				$write_to_struct = QPHPToken::ParsePHPFile($element->classPath);
				$patch_code_path = QAutoload::GetCompiledPath($sync_item->class_merge_node->classPath);
				$patch_php_code = QPHPToken::ParsePHPFile($patch_code_path, false);

				$patch_php_code->findFirst(".QPHPTokenClass")->mergeWithClass($write_to_struct->findFirst(".QPHPTokenClass"), true);

				// self::filePutContentsIfChanged(QAutoload::GetCompiledPath($element->classPath), $write_to_struct."", true);
				QCodeSync::syncCompiledCode($element->classPath, $patch_php_code);
			}
			 */

			$this->debugLog[$element->watchFolder][$element->className]["url"][$tag] = array("fp" => $full_path, "log" => $debug_log);
		}
		finally
		{
			$this->benchmark("resyncUrlController", $__t0, func_get_args());
		}
	}
}
