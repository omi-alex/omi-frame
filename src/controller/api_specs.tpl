<!DOCTYPE html>
<html lang="en" qCtrl="OmiframeWebsite\WebPage">
	<head>
		<!-- Meta, title, CSS, favicons, etc. -->
		<meta charset="utf-8" />
		<meta http-equiv="X-UA-Compatible" content="IE=edge" />
		<meta name="viewport" content="width=device-width, initial-scale=1.0" />
		<base href="" />
		<title>OMIFrame Development Specs - by OMIBIT.com</title>
		<meta name="description" content="OMIFrame PHP is about productive, fast, simple, yet surprisingly powerful and innovative" />
		<meta name="keywords" content="php framework object-oriented productive fast powerful innovative" />
		<meta name="author" content="Alex Stanciu" />
		<!-- Latest compiled and minified CSS -->
		<link rel="stylesheet" href="//netdna.bootstrapcdn.com/bootstrap/3.0.3/css/bootstrap.min.css" />

		<!-- Optional theme -->
		<link rel="stylesheet" href="//netdna.bootstrapcdn.com/bootstrap/3.0.3/css/bootstrap-theme.min.css" />

		<link href="//netdna.bootstrapcdn.com/font-awesome/4.0.3/css/font-awesome.css" rel="stylesheet" />

		<script src="//code.jquery.com/jquery-1.10.2.min.js"></script>
		<link rel="stylesheet" href="//www.omiframe.com/style/style.css" />

	</head>
	<body>
		<div class="mainwrap">
			<div class="logo"><a href=''><img src="//www.omiframe.com/i/logo.png"/></a></div>
			<div class="small_links">
				<!-- <a href="#">Contact </a>
				<a href="#">Log in |</a>
				<a href="#">My account |</a> -->
			</div>
			<div style="clear: both;"><!-- --></div> 
			<div class="menu_wraper">
				<div class="second-menu-page">
					<a href="">RESTFul API</a>
				</div>
				<div style="clear: both;"><!-- --></div>
			</div>
		</div>
		<div class="header-strip">
			<div class="header-strip left"><!-- --></div>
			<div class="header-strip right"><h2>Welcome</h2></div>
		</div>
		<div style="clear: both;"><!-- --></div>
		<div class="mainwrap">
			<div style="clear: both;"><!-- --></div>
			<div qnamespace="OmiframeWebsite" qctrl="content(OmiframeWebsite\DocsCtrl)" class="QWebControl">
				<nav class="docs-tree">
					<ul qargs="$menu, $skip_first = false, $depth = 0" class="nav">
						<li>
							<span>Getting Started</span>
							<ul qargs="$menu, $skip_first = false, $depth = 0" class="nav">
								<li><a href="#intro">Introduction</a></li>
								<li><a href="#login">Log-in</a></li>
							</ul>
						</li>
					</ul>
				</nav>
				<div class="docs-content">
					<!-- Caption: Getting started -->

					<div class="page-header">
						<h1>RESTFul API Documentation</h1>
					</div>
					
					<a name="intro"></a>
					<h4>Introduction</h4>

					<p>
					</p>
					<br />

					<a name="login"></a>
					<h4>Log-in</h4>
					<ul>
						<li><b>URL: </b>~API/RESTful/login</li>
						<li><b>GET: </b><i>null</i></li>
						<li><b>JSON [RAW POST]: </b> <code>{"user": "john", "pass": "1234"}</code></li>
						<li><b>Response: </b> <code>true</code> or <code>false</code></li>
					</ul>
					<p>
						The Authentication is COOKIE based via PHPSESSID
					</p>
					
					<p>
					</p>
					
					<a name="general-view"></a>
					<h4>General Info About Listing and View</h4>
					
					<ul>
						<li>Optional GET parameters:
							<ul>
								<li>You can limit the data you receive by using <code>selectors</code></li>
								<li><code>selector=Id,Name</code> </li>
								<li>see info about <code>selectors</code> <a href="http://www.omiframe.com/docs/model-selectors">here</a></li>
							</ul>
						</li>
					</ul>
					
					<?php
						
						$storage_model = \QApp::GetDataClass();
						$root_ty = \QModel::GetTypeByName($storage_model);
						// qvar_dump($root_ty);
						// die;
					
						foreach (\QWebRequest::GetAPIAllowedProperties() as $k => $v)
						{
							$property_refl = $root_ty ? ($root_ty->properties[$k] ?: $root_ty->properties[$v]) : null;
							$analyze_start_type = $property_refl ? $property_refl->getFirstInstantiableType() : null;
							
							if ($property_refl && $analyze_start_type)
							{
								$property_reflection = null;
								$is_collection = null;
								$storage_model = QApp::GetDataClass();
								$src_from_types = \QApi::DetermineFromTypes($storage_model, $property_refl->name, $is_collection, $property_reflection);
								if (is_array($src_from_types))
									$src_from_types = reset($src_from_types);
								$analyze_query = $src_from_types::GetListingQuery(null, $property_refl->name);
								
								// $analyze_query = ($property_refl && $analyze_start_type) ? 
								//		($storage_model::GetPropertyListingQuery($property_refl->name) ?: $analyze_start_type::GetListingQuery()) : null;
							}
							$analyze_result = $analyze_query ? \QQueryAnalyzer::Analyze($analyze_query, $analyze_start_type) : null;
							
							// qvar_dump($property_refl->name, $analyze_query, $analyze_result['__binds__']);
							// die;
							
							
							?>
							<a name="prop-<?= htmlentities($k) ?>-list"></a>
							<h4><?= htmlentities($k) ?> Listing</h4>
							<ul>
								<li><b>URL: </b>~API/RESTful/<?= htmlentities($k) ?></li>
								<li><b>GET: </b><code>selector</code> - optional</li>
								<?php
								if ($analyze_result['__binds__'])
								{
									?><li><b>JSON [RAW POST]: </b> 
										<table>
											<tr>
												<th>Key</th>
												<th>Role</th>
												<th>Data Type</th>
												<th></th>
											</tr>
											<?php
												foreach ($analyze_result['__binds__'] as $param_k => $param_inf)
												{
													$continue = false;
													switch ($param_k)
													{
														case "Gid":
														case "NoGid":
														case "HasGid":
														case "Owner":
														case "InSyncProcess":
														case "ToBeSynced":
														case "LastSyncedAt":
														case "CreatedBy":
														case "SuppliedBy":
														{
															$continue = true;
															break;
														}
													}
													if ($continue)
														continue;
													
													if ($param_k === 'LIMIT[')
													{
														?>
														<tr>
															<td><code>LIMIT</code></td>
															<td><code>LIMIT</code></td>
															<td><code>integer</code></td>
															<td>example: <code>{"LIMIT": [10, 30]}</code> - optional</td>
														</tr>
														<?php
													}
													else
													{
														$pi_type = $param_inf["__d__"]['first_idf']['_ty'][0];
														if ($param_inf["__d__"]["zone"] === "ORDER BY")
															$pi_type = 'boolean';
														if (!$pi_type)
															$pi_type = 'integer';
														?>
														<tr>
															<td><code><?= $param_k ?></code></td>
															<td><code><?= $param_inf["__d__"]["zone"] ?></code></td>
															<td><code><?= $pi_type ?></code></td>
															<td>optional</td>
														</tr>
														<?php
													}
												}
											?>
										</table>
									</li>
									<?php
								}
								?>
								<li><b>Response: </b> A list of <?= htmlentities($k) ?></li>
							</ul>
							
							<a name="prop-<?= htmlentities($k) ?>-view"></a>
							<h4><?= htmlentities($k) ?> Details</h4>
							<ul>
								<li><b>URL: </b>~API/RESTful/<?= htmlentities($k) ?>/view/$id</li>
								<li><b>GET: </b><code>selector</code> - optional</li>
								<!-- <li><b>JSON [RAW POST]: </b> <code>{"user": "john", "pass": "1234"}</code></li>-->
								<li><b>Response: </b> One <?= htmlentities(substr($k, 0, -1)) ?> with details</li>
							</ul>
							
							<?php
						}
					?>
					
				</div>
			</div>
		</div>
		<div style="clear: both;"><!-- --></div>
	</body>
</html>