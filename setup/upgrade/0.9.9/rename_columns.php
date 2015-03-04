<?php
   /**********************************************************\
   | This script renames columns, adodb seems to have prob here|
   \**********************************************************/

$dict = NewDataDictionary($db->dblink);

$sqlarray = $dict->RenameColumnSQL($fsconf['database']['dbprefix'] . 'tasks', 'attached_to_project', 'project_id', 'TYPE INT(3) NOTNULL  DEFAULT 0');
$dict->ExecuteSQLArray($sqlarray);

$sqlarray = $dict->RenameColumnSQL($fsconf['database']['dbprefix'] . 'groups', 'belongs_to_project', 'project_id', ' TYPE INT(3) NOTNULL  DEFAULT 0');
$dict->ExecuteSQLArray($sqlarray);

?>