<?php

class clsDatabaseSchema
{
	private $dbhandle;
		
	public function clsDatabaseSchema($server = 'localhost', $user = 'root', $pwd = 'root')
	{
		$this->connection($server, $user, $pwd);
	}
	
	public function connection($server, $user, $pwd)
	{
		// connection to the database 
		$this->dbhandle = mysql_connect($server, $user, $pwd)
		   or die("Unable to connect to MySQL"); 
	}
	
	public function ListTables( $db )
	{
	  $ret = array();
	  $query  = "SELECT
				t.table_name AS 'Table',
				t.table_comment AS 'Comment'
				FROM information_schema.tables t
				WHERE t.table_schema = '$db'";
	  
	    $res = mysql_query( $query, $this->dbhandle );
	    if( !is_bool( $res )) 
	    {
	    	$c = 0 ; 
			while ($row = mysql_fetch_row($res)) { 
			   $ret[$c][0] = $row[0]; 

			   $comment =  $row[1];
			   //if(substr( strrchr( $comment , "-" ) , 1 ) != "")
			   //		$comment =  substr( strrchr( $comment , "-" ) , 1 );
			   $ret[$c][1] = $comment;
			   
			   $c = $c + 1 ; 
			} 
	    }
	    return $ret;
	}
	
	public function ListCommentColumn( $db, $table, $column )
	{
	  $ret = array();
	  $query  = "SELECT
				  column_comment AS 'Comment'
				FROM information_schema.tables t
				JOIN information_schema.columns c ON t.table_schema = c.table_schema AND t.table_name = c.table_name
				WHERE t.table_schema = '$db' and t.table_name = '$table' and c.column_name = '$column'";
	  
	    $res = mysql_query( $query, $this->dbhandle );
	    if( !is_bool( $res )) 
	    {
			while ($row = mysql_fetch_row($res)) { 
			   $ret[] = $row[0]; 
			} 
	    }
	    $comment =  $ret[0];
	    //if(substr( strrchr( $comment , "-" ) , 1 ) != "")
	    //	return substr( strrchr( $comment , "-" ) , 1 );
	    //else
	    	return $comment;
	   
	}
	
	public function ListColumns( $db, $table)
	{
	  $ret = array();
	  
	  $query  = "SELECT
				  c.column_name AS 'Column'
				  ,c.data_type AS 'Data Type',
				  column_comment AS 'Comment'
				FROM information_schema.tables t
				JOIN information_schema.columns c ON t.table_schema = c.table_schema AND t.table_name = c.table_name
				WHERE t.table_schema = '$db' and t.table_name = '$table'";
	  
	    $res = mysql_query( $query, $this->dbhandle );
	    if( !is_bool( $res )) 
	    {
	    	$c = 0;
			while ($row = mysql_fetch_row($res)) {
			   $ret[$c][0] = $row[0]; 
			   $ret[$c][1] = $row[1]; 
			   
			   $comment =  $row[2];
			   //if(substr( strrchr( $comment , "-" ) , 1 ) != "")
			   //		$comment =  substr( strrchr( $comment , "-" ) , 1 );
			   $ret[$c][2] = $comment;
			   
			   $c = $c + 1 ; 
			} 
	    }
	    return $ret;
	}
	
	public function ListPrimaryKeys( $db, $table)
	{
	  $ret = array();
	  
	  $query  = "SELECT k.column_name 
				FROM information_schema.table_constraints t 
				JOIN information_schema.key_column_usage k 
				USING (constraint_name,table_schema,table_name)
				WHERE t.constraint_type='PRIMARY KEY' 
				  AND t.table_schema='$db'
				  AND t.table_name='$table'";
	  
	    $res = mysql_query( $query, $this->dbhandle );
	    if( !is_bool( $res )) 
	    {
			while ($row = mysql_fetch_row($res)) { 
			   $ret[] = $row[0]; 
			} 
	    }
	    return $ret;
	}
	
	public function ListForeignKeys( $db, $table )
	{
		$arrayForeignKeys = array( ) ;
		$array = array( ) ;
		$arrayReturn = array( ) ;
		
		$arrayForeignKeys = $this->ListParentsForDb( $db );
		for( $c = 0 ; $c < count($arrayForeignKeys) ; $c = $c + 1 )
		{
			if( $arrayForeignKeys[ $c ][ 1 ] == $table )
			{
				$array[ ] = $arrayForeignKeys[ $c ][ 1 ] ;
				$array[ ] = $arrayForeignKeys[ $c ][ 2 ] ;
				$array[ ] = $arrayForeignKeys[ $c ][ 4 ] ;
				$array[ ] = $arrayForeignKeys[ $c ][ 5 ] ;
				$arrayReturn[ ] = $array;
				unset($array);
			}
		}
		return $arrayReturn;
	}
	
	public function childtables( $db, $table, $via_infoschema=FALSE ) {
	  $ret = array();
	  if( $via_infoschema ) {
	    $res = mysql_query( $this->childtablesqry( $db, $table ),$this->dbhandle);
	    if( !is_bool( $res )) 
	    {
	      while( $row = mysql_fetch_row( $res )) 
	        $ret[] = $row;
	    }
	  }
	  else {
	    $tables = array();
	    $res = mysql_query( "SHOW TABLES" );
	    while( $row = mysql_fetch_row( $res )) $tables[] = $row[0];
	    $res = mysql_query( "SELECT LOCATE('ANSI_QUOTES', @@sql_mode)" );
	    $ansi_quotes = $res ? mysql_result( $res, 0 ) : 0;
	    $q = $ansi_quotes ? '"' : "`";
	    $sref = ' REFERENCES ' . $q . $table . $q . ' (' . $q;
	    foreach( $tables as $referringtbl ) {
	      $res = mysql_query( "SHOW CREATE TABLE $referringtbl" );
	      $row = mysql_fetch_row( $res );
	      if(( $startref = stripos( $row[1], $sref )) > 0 ) {
	        $endref = strpos( $row[1], $q, $startref + strlen( $sref ));
	        $referencedcol = substr( $row[1], $startref+strlen($sref), 
	                                 $endref-$startref-strlen($sref) );
	        $endkey = $startref; 
	        while( substr( $row[1], $endkey, 1 ) <> $q ) $endkey--;
	        $startkey = --$endkey;
	        while( substr( $row[1], $startkey, 1 ) <> $q ) $startkey--;
	        $referencingcol = substr( $row[1], $startkey+1, $endkey - $startkey );
	        $ret[] = array( $db, $referringtbl, $referencingcol, $referencedcol );
	      }
	    }
	  }
	  return $ret;
	}
	
	protected function childtablesqry( $db, $table ) {
	  return "SELECT c.table_schema,u.table_name,u.column_name,u.referenced_column_name " .
	         "FROM information_schema.table_constraints AS c " .
	         "INNER JOIN information_schema.key_column_usage AS u " .
	         "USING( constraint_schema, constraint_name ) " .
	         "WHERE c.constraint_type = 'FOREIGN KEY' " .
	         "AND u.referenced_table_schema='$db' " .
	         "AND u.referenced_table_name = '$table' " .
	         "ORDER BY c.table_schema,u.table_name";
	} 
	
	public function ListParentsForTable( $db, $table) {
	  $ret = array();
	  $res = mysql_query( $this->ListParentsForTableQuery( $db, $table ),$this->dbhandle);
	  if( !is_bool( $res )) 
	  {
	    while( $row = mysql_fetch_row( $res )) 
	    	$ret[] = $row;
	  }
	  return $ret;
	}
	
	protected function ListParentsForTableQuery( $db, $table ) {
		return "SELECT 
	   u.table_schema AS 'Schema',
	   u.table_name AS 'Table',
	   u.column_name AS 'Key',
	   u.referenced_table_schema AS 'Parent Schema',
	   u.referenced_table_name AS 'Parent table',
	   u.referenced_column_name AS 'Parent key'
	  FROM information_schema.table_constraints AS c
	  INNER JOIN information_schema.key_column_usage AS u
	  USING( constraint_schema, constraint_name )
	  WHERE c.constraint_type = 'FOREIGN KEY'
	    AND c.table_schema = '$db'
	    AND u.referenced_table_name = '$table'
	  ORDER BY u.table_schema,u.table_name,u.column_name";
	}
	
	public function ListParentsForDb( $db) {
	  $ret = array();
	  $res = mysql_query( $this->ListParentsForDbQuery( $db ),$this->dbhandle);
	  if( !is_bool( $res )) 
	  {
	    while( $row = mysql_fetch_row( $res )) 
	    	$ret[] = $row;
	  }
	  return $ret;
	}
	
	protected function ListParentsForDbQuery($db){
		return "SELECT 
	   u.table_schema AS 'Schema',
	   u.table_name AS 'Table',
	   u.column_name AS 'Key',
	   u.referenced_table_schema AS 'Parent Schema',
	   u.referenced_table_name AS 'Parent table',
	   u.referenced_column_name AS 'Parent key'
	  FROM information_schema.table_constraints AS c
	  INNER JOIN information_schema.key_column_usage AS u
	  USING( constraint_schema, constraint_name )
	  WHERE c.constraint_type = 'FOREIGN KEY'
	    AND c.table_schema = '$db'
	  ORDER BY u.table_schema,u.table_name,u.column_name";
	}
	
}

?>