<!DOCTYPE html<?php $_qBHV = QWebPage::BrowserHtmlVersion(); echo ($_qBHV >= 5) ? "" : " PUBLIC \"-//W3C//DTD XHTML 1.0 Transitional//EN\" \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd\""; ?>>
<html<?php echo ($_qBHV >= 5) ? "" : " xmlns=\"http://www.w3.org/1999/xhtml\""; ?>>
	<head>
		<META HTTP-EQUIV="Content-Type" CONTENT="text/html; charset=utf-8"/>
		<title></title>
		<base href="<?= BASE_HREF; ?>" />
		
		<?php if (file_exists(QAutoload::GetRuntimeFolder()."temp/js_paths.js")) {
		?><script type="text/javascript" src="<?= QAutoload::GetMainFolderWebPath() ?>temp/js_paths.js"></script><?php } ?>
		<script type="text/javascript" src="<?= Q_FRAME_REL; ?>view/js/stacktrace.js"></script>
		<script type="text/javascript" src="<?= Q_FRAME_REL; ?>view/js/phpjs.js"></script>
		<script type="text/javascript" src="<?= Q_FRAME_REL; ?>view/js/jquery-2.1.4.min.js"></script>
		<script type="text/javascript" src="<?= Q_FRAME_REL; ?>view/js/functions.js"></script>
		<script type="text/javascript" src="<?= \QAutoload::GetTempWebPath("model_type.js"); ?>"></script>
		<script type="text/javascript" src="<?= Q_FRAME_REL; ?>base/QObject.js"></script>
		<script type="text/javascript" src="<?= Q_FRAME_REL; ?>view/js/mvvm.js"></script>

		<script type="text/javascript">
			<?php echo "var QApp = {\"DataClass\" : ". (QApp::Data() ? "\"".get_class(QApp::Data())."\"" : "null")." , \"DataId\" : ".(QApp::Data() ? "\"".QApp::Data()->getId()."\"" : "null")."};"; ?>
		</script>
		<!-- <script type="text/javascript" src="<?= Q_FRAME_REL; ?>view/js/init.js"></script> -->
	</head>
	<body>
		<?php
		
			if ($this->children)
			{
				foreach ($this->children as $child)
					$child->render();
			}
			else
				$this->renderBody();
			
			$this->renderCallbacks();
		?>
	</body>
</html>