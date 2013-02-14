<?php

class DBSQLite
{
	// absolute path to db files
	private static $sDBPath = DIR_DB_SQLITE;


	// last insert id
	private $iLastInsertRowID = 0;


	// db object
	private $oDB = NULL;


	/**
	 * 
	 * @return object
	 */
	public static function getSQLiteObject()
	{
		return Registry::get('DB');
	}


	/**
	 * the constructor
	 * @param string $sDBName
	 * @return \dbsqlite
	 * @throws Exception
	 */
	public function __construct($sDBName)
	{
		// initialize db
		$this->oDB = new SQLite3(self::$sDBPath . '/' . $sDBName);

		// check if initialize was successfully
		if(!is_object($this->oDB))
		{
			$this->oDB = null;
			throw new Error('Error while trying to initialize DB');
		}

		return $this;
	}
	
	
	/**
	 * 
	 * @param string $sMethodName
	 * @param array $aArguments
	 * @return mixed
	 * @throws Error
	 */
	public function __call($sMethodName, $aArguments)
	{
		if(method_exists($this, $sMethodName . 'P'))
		{
			return call_user_func_array(array($this, $sMethodName . 'P'), $aArguments);
		}
		elseif(method_exists($this, $sMethodName))
		{
			return call_user_func_array(array($this, $sMethodName), $aArguments);
		}
		else
		{
			throw new Error('Method: "' . $sMethodName . '" does not exist in Class: "' . get_class($this) . '"');
		}
	}

	
	/**
	 * 
	 * @param string $sMethodName
	 * @param array $aArguments
	 * @return mixed
	 * @throws Error
	 */
	public static function __callStatic($sMethodName, $aArguments)
	{
		if(method_exists(__CLASS__, $sMethodName . 'S'))
		{
			return call_user_func_array(array(__CLASS__, $sMethodName . 'S'), $aArguments);
		}
		elseif(method_exists(__CLASS__, $sMethodName))
		{
			return call_user_func_array(array(__CLASS__, $sMethodName), $aArguments);
		}
		else
		{
			throw new Error('Method: "' . $sMethodName . '" does not exist in Class: "' . __CLASS__ . '"');
		}
	}


	
	/**
	 * debug query
	 * @param string $sSQL
	 * @param array $aSQL
	 */
	public function debugP($sSQL, $aSQL = array())
	{
		$sSQL = $this->replace($sSQL, $aSQL);
		__out($sSQL);
	}
	
	
	/**
	 * debug query
	 * @param string $sSQL
	 * @param array $aSQL
	 */
	public static function debugS($sSQL, $aSQL = array())
	{
		$oDB = self::getSQLiteObject();

		$oDB->debugP($sSQL, $aSQL);
	}

	
	/**
	 * replace and escape string
	 * @param string $sSQL
	 * @param array $aSQL
	 * @return string
	 * @throws Error
	 */
	private function replace($sSQL, $aSQL)
	{
		if(!empty($aSQL))
		{
			// Sort keys by length DESC
			uksort($aSQL, array('sort', 'sortByLength'));

			foreach((array)$aSQL as $sKey => $mValue)
			{
				if(is_string($mValue))
				{
					$mValue = $this->oDB->escapeString($mValue);
					
					$sSQL = str_replace(':' . $sKey, '\'' . $mValue . '\'', $sSQL);
					$sSQL = str_replace('#' . $sKey, '`' . $mValue . '`', $sSQL);
				}
				elseif(is_int($mValue) || is_float($mValue))
				{	
					$sSQL = str_replace(':' . $sKey, $mValue, $sSQL);
				}
				else
				{
					throw new Error('What is Value in aSQL? (no string no int)');
				}
			}
		}
		
		return $sSQL;
	}


	/**
	 * query the prepared string
	 * @param string $sSQL
	 * @param array $aSQL
	 * @return object
	 * @throws Error
	 */
	public function queryP($sSQL, $aSQL = array())
	{
		$sSQL = $this->replace($sSQL, $aSQL);

		// execute query - prevent warning/error msg from php
		$mQueryReturn = @$this->oDB->query($sSQL);

		// on query error - show error
		if(!$mQueryReturn)
		{
			// get and show last error
			$sQueryErrorMessage = $this->oDB->lastErrorMsg();
			throw new Error($sQueryErrorMessage);
		}

		// get last insert row id : 0 if none
		$this->iLastInsertRowID = $this->oDB->lastInsertRowID();

		// return query result object
		return $mQueryReturn;
	}

	
	public static function queryS($sSQL, $aSQL = array())
	{
		$oDB = self::getSQLiteObject();

		return $oDB->queryP($sSQL, $aSQL);
	}
	

	/**
	 * query single the prepared string
	 * @param string $sSQL
	 * @param array $aSQL
	 * @param boolean $bEntireRow
	 * @return mixed
	 * @throws Error
	 */
	private function querySingle($sSQL, $aSQL = array(), $bEntireRow = false)
	{
		$sSQL = $this->replace($sSQL, $aSQL);

		// execute query - prevent warning/error msg from php
		$mResult = @$this->oDB->querySingle($sSQL, $bEntireRow);

		// on query error - show error
		if($mResult === false)
		{
			// get and show last error
			$sQueryErrorMessage = $this->oDB->lastErrorMsg();
			throw new Error($sQueryErrorMessage);
		}

		// query single without entire row returns NULL on empty result ...
		if($mResult === null)
		{
			$mResult = array();
		}

		// get last insert row id : 0 if none
		$this->iLastInsertRowID = $this->oDB->lastInsertRowID();

		// return query result object
		return $mResult;
	}


	/**
	 * get last inserted id
	 * @return integer
	 */
	public function lastInsertRowIDP()
	{
		return (int)$this->iLastInsertRowID;
	}
	

	/**
	 * get last inserted id
	 * @return integer
	 */
	public static function lastInsertRowIDS()
	{
		$oDB = self::getSQLiteObject();

		return $oDB->lastInsertRowIDP();
	}


		/**
	 * fetch all
	 * @param string $sSQL
	 * @param array $aSQL
	 * @return array
	 */
	public function fetchAllP($sSQL, $aSQL = array())
	{
		$oResult = $this->queryP($sSQL, $aSQL);
		
		$aResult = array();
		
		while($aRow = $oResult->fetchArray(SQLITE3_ASSOC))
		{
			$aResult[] = $aRow;
		}

		$oResult->finalize();

		return $aResult;
	}
	
	
	/**
	 * fetch all
	 * @param string $sSQL
	 * @param array $aSQL
	 * @return array
	 */
	public static function fetchAllS($sSQL, $aSQL = array())
	{
		$oDB = self::getSQLiteObject();

		return $oDB->fetchAllP($sSQL, $aSQL);
	}


	/**
	 * fetch pairs
	 * @param string $sSQL
	 * @param array $aSQL
	 * @return array
	 */
	public function fetchPairsP($sSQL, $aSQL = array())
	{
		$oResult = $this->queryP($sSQL, $aSQL);

		$aResult = array();

		while($aRow = $oResult->fetchArray(SQLITE3_NUM))
		{
			$aResult[$aRow[0]] = $aRow[1];
		}

		$oResult->finalize();

		return $aResult;
	}
	
	
	/**
	 * fetch pairs
	 * @param string $sSQL
	 * @param array $aSQL
	 * @return array
	 */
	public static function fetchPairsS($sSQL, $aSQL = array())
	{
		$oDB = self::getSQLiteObject();

		return $oDB->fetchPairsP($sSQL, $aSQL);
	}


	/**
	 * fetch one
	 * @param string $sSQL
	 * @param array $aSQL
	 * @return mixed
	 */
	public function fetchOneP($sSQL, $aSQL = array())
	{	
		$mResult = $this->querySingle($sSQL, $aSQL);

		return $mResult;
	}
	
	
	/**
	 * fetch one
	 * @param string $sSQL
	 * @param array $aSQL
	 * @return mixed
	 */
	public static function fetchOneS($sSQL, $aSQL = array())
	{
		$oDB = self::getSQLiteObject();

		return $oDB->fetchOneP($sSQL, $aSQL);
	}


	/**
	 * fetch col
	 * @param string $sSQL
	 * @param array $aSQL
	 * @return array
	 */
	public function fetchColP($sSQL, $aSQL = array())
	{
		$oResult = $this->queryP($sSQL, $aSQL);
		
		$aResult = array();

		while($aRow = $oResult->fetchArray(SQLITE3_NUM))
		{
			$aResult[] = $aRow[0];
		}

		$oResult->finalize();

		return $aResult;
	}
	
	
	/**
	 * fetch col
	 * @param string $sSQL
	 * @param array $aSQL
	 * @return array
	 */
	public static function fetchColS($sSQL, $aSQL = array())
	{
		$oDB = self::getSQLiteObject();

		return $oDB->fetchColP($sSQL, $aSQL);
	}


	/**
	 * fetch row
	 * @param string $sSQL
	 * @param array $aSQL
	 * @return array
	 */
	public function fetchRowP($sSQL, $aSQL = array())
	{
		$oResult = $this->queryP($sSQL, $aSQL);

		$aResult = array();

		$aRow = $oResult->fetchArray(SQLITE3_ASSOC);

		foreach($aRow as $sColName => $sColValue)
		{
			$aResult[$sColName] = $sColValue;
		}

		$oResult->finalize();

		return $aResult;
	}
	
	
	/**
	 * fetch row
	 * @param string $sSQL
	 * @param array $aSQL
	 * @return array
	 */
	public static function fetchRowS($sSQL, $aSQL = array())
	{
		$oDB = self::getSQLiteObject();

		return $oDB->fetchRowP($sSQL, $aSQL);
	}
	
	public function insertP($sTable, $aData = array())
	{
		if(
			!is_string($sTable) ||
			trim($sTable) == '' ||
			!is_array($aData)	||
			empty($aData)
		)
		{
			throw new Error('DB - insert: parameters wrong');
		}
		
		// check if table exists
		$aSQL = array(
			'sTable' => $sTable
		);
		$sSQL = "
			SELECT
				COUNT(*)
			FROM
				`sqlite_master`
			WHERE
				`type` = 'table' AND
				`name` = :sTable
		";
		$iCountTable = $this->fetchOneP($sSQL, $aSQL);

		if((int)$iCountTable !== 1)
		{
			throw new Error('DB - insert: table does not exist');
		}

		// get table informations
		$aSQL = array(
			'sTable' => $sTable
		);
		$sSQL = "
			PRAGMA table_info(#sTable);
		";
		$aTableInformations = $this->fetchAllP($sSQL, $aSQL);

		if(empty($aTableInformations))
		{
			throw new Error('DB - insert: table got no cols');
		}

		// get available cols
		$aAvailableCols = array();
		foreach((array)$aTableInformations as $iColKey => $aCol)
		{
			$aAvailableCols[$aCol['name']] = true;
		}

		// check if cols in data array exists in available cols
		foreach((array)$aData as $sColName => $mColValue)
		{
			if(!isset($aAvailableCols[$sColName]))
			{
				throw new Error('DB - insert: col "' . $aCol['name'] . '" does not exist');
			}
		}

		// get cols only as array
		$aCols = array_keys($aData);

		// prepare asql
		$aSQL = array();
		foreach((array)$aCols as $iColKey => $sColName)
		{
			$aSQL['col_name_' . $iColKey]	= $sColName;
			$aSQL['col_value_' . $iColKey]	= $aData[$sColName];
		}

		// create insert query
		$sSQLInsertHeader = $sSQLInsertBody = "";
		$sEscapedTablename = $this->oDB->escapeString($sTable);
		foreach((array)$aCols as $iColKey => $sColName)
		{
			$sSQLInsertHeader .= "#col_name_" . $iColKey;
			$sSQLInsertBody .= ":col_value_" . $iColKey;

			if(isset($aCols[$iColKey + 1]))
			{
				$sSQLInsertHeader .= ", ";
				$sSQLInsertBody .= ", ";
			}
		}
		$sSQL = "
			INSERT INTO
				`" . $sEscapedTablename . "`
				(" . $sSQLInsertHeader . ")
			VALUES
				(" . $sSQLInsertBody . ")
		";
		$this->queryP($sSQL, $aSQL);

		// get last insert id
		$iLastInsertID = $this->lastInsertRowIDP();

		return $iLastInsertID;
	}
	
	public static function insertS($sTableName, $aData = array())
	{
		$oDB = self::getSQLiteObject();

		return $oDB->insertP($sTableName, $aData);
	}
}