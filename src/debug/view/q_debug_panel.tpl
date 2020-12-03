<?php

	$selected_request_id = $_GET['request'];
	$latest_requests = $this->trace->get_last_requests();
	$request = $selected_request_id ? $latest_requests[$selected_request_id] : null;

?><!doctype html>
<html>
	<head>
		<title>Debug Panel</title>
		<base href="<?= BASE_HREF ?>" />
		
		<style type="text/css">
			
			table td {
				vertical-align: top;
			}
			
			.styled-table {
				border-collapse: collapse;
				margin: 25px 0;
				font-family: monospace;
				font-size: 12px;
				min-width: 400px;
				box-shadow: 0 0 20px rgba(0, 0, 0, 0.15);
				table-layout: fixed;
				width: 100%;
			}
			
			.styled-table thead tr {
				background-color: #009879;
				color: #ffffff;
				text-align: left;
			}
			
			.styled-table th,
			.styled-table td {
				padding: 2px 2px;
			}
			
			.styled-table tbody tr {
				border-bottom: 1px solid #dddddd;
			}
			
			.styled-table tbody tr:hover {
				background-color: lightgoldenrodyellow;
			}
			
			/*
			.styled-table tbody tr:nth-of-type(even) {
				background-color: #f3f3f3;
			}
			*/
			.styled-table tbody tr:last-of-type {
				border-bottom: 2px solid #009879;
			}
			
			.styled-table tbody tr.active-row {
				font-weight: bold;
				color: #009879;
			}
			
			.styled-table td.big {
				width: 100px;
			}
			
			.styled-table td.tags {
				width: 100px;
			}
			
			div.scrollable {
				width: 100%;
				height: 100%;
				margin: 0;
				padding: 0;
				overflow: auto;
				
				display: none;
			}
			
		</style>
		
		<script type="text/javascript">
			window.document.addEventListener("click", function ($event) {
				var $dom = $event.target;
				if (($dom.tagName === 'A') && $dom.classList.contains('qdbg'))
				{
					// alert($dom.tagName);
					var $div = $dom.closest('td').querySelector('div');
					alert($div.innerText);
				}
			});
		</script>
		
	</head>
	<body>
		
		<h5><a href="?<?= Q_DEBUG_API_KEY ?>">Home</a><?php
		
		if ($selected_request_id)
			echo " | ".($request ? $request['caption']." - " : "").$selected_request_id;
		
		?></h5>
		
		<?php
		
			if ($selected_request_id)
			{
				if (!$request)
					echo "<p style='color:red;'>Missing REQUEST DATA !!</p>";
				else
				{
					echo "<table class='styled-table'>";
					$tree = $this->trace->get_request_traces($request, true);
					//qvar_dumpk($tree);
					$this->render_nodes([$tree]);
					echo "</table>";
				}
			}
			else
			{
				?>
				<h5>Latest Requests (by date DESC)</h5>
				<ul><?php
				foreach ($latest_requests as $request_id => $lr)
				{
					?><li><a href="?<?= Q_DEBUG_API_KEY ?>&request=<?= $request_id ?>"><?= ($lr ? $lr['caption']." - " : "").$request_id ?></a></li><?php
				}
				echo "</ul>";
			}
		?>
	</body>
</html>

