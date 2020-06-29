<?php

/**
 * QSql
 *
 * @author Alex
 */
class QSql
{
	const DESC = 1;
	const ASC = 2;
	
	public static function Begin()
	{
		QApp::GetStorage()->begin();
	}
	public static function Commit()
	{
		QApp::GetStorage()->commit();
	}
	public static function Rollback()
	{
		QApp::GetStorage()->rollback();
	}
}
