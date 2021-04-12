<?php

/**
 * QWebPage
 */
class QWebPage extends QWebControl implements QIUrlController
{
	use QWebPage_GenTrait, QWebPage_Methods;
	
	public $docType = "<!doctype html>\n";

}
