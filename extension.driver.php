<?php

	Class extension_dump_db extends Extension{

		public function about(){
			return array('name' => 'Dump DB',
						 'version' => '1.08',
						 'release-date' => '2011-02-02',
						 'author' => array('name' => 'Nils Werner',
										   'website' => 'http://www.phoque.de',
										   'email' => 'nils.werner@gmail.com')
				 		);
		}
		
		public function getSubscribedDelegates(){
			return array(
						array(
							'page' => '/system/preferences/',
							'delegate' => 'AddCustomPreferenceFieldsets',
							'callback' => 'appendPreferences'
						),
						array(
							'page'		=> '/backend/',
							'delegate'	=> 'InitaliseAdminPageHead',
							'callback'	=> 'initaliseAdminPageHead'
						),
						array(
							'page' => '/backend/',
							'delegate' => 'AppendPageAlert',
							'callback' => 'appendAlert'
						),
					);
		}
		
		public function __construct() {
			$this->path = General::Sanitize(Symphony::Configuration()->get('path', 'dump_db'));
			$this->format = General::Sanitize(Symphony::Configuration()->get('format', 'dump_db'));
			
			if($this->format == "")
				$this->format = '%1$s.sql';
			
			if($this->path == "")
				$this->path = "/workspace";
		}
		
		public function install(){
			return true;
		}
		
		public function uninstall(){
				Symphony::Configuration()->remove('dump_db');            
				Administration::instance()->saveConfig();
		}
		
		public function initaliseAdminPageHead($context) {
			$page = $context['parent']->Page;
			
			$page->addScriptToHead(URL . '/extensions/dump_db/assets/dump_db.preferences.js', 3134);
		}
		
		public function appendAlert($context){
			
			if(!is_null($context['alert'])) return;
			
		    if ($this->__filesNewer()) {
				$files = implode(__(" and "), array_map('__',array_map('ucfirst',$this->__filesNewer())));
			
		        if(count($this->__filesNewer()) == 1)
		        	$message = __('The database file for your %s is newer than your last sync. ',array($files));
		        else
		        	$message = __('The database files for both your %s is newer than your last sync. ',array($files));
		        	
		        	
		        $message .= __('It\'s recommended to <a href="%s">sync your database now.</a>', array(URL . '/symphony/system/preferences/#dump-actions'));
		        
       			Administration::instance()->Page->pageAlert($message);
		    }
		    
		    

		}
		
		public function appendPreferences($context){
			$downloadMode = $this->__downloadMode();
			$filesWriteable = $this->__filesWriteable();
			
		    if (count($filesWriteable) < 2 && !$downloadMode && !$this->__filesNewer()) {
		        Administration::instance()->Page->pageAlert(__('At least one of the database-dump files is not writeable. You will not be able to save your database.'), Alert::ERROR);
		    }
			
			if(isset($_POST['action']['dump'])){
				$this->__dump($context);
			}
			
			if(isset($_POST['action']['restore'])){
				$this->__restore($context);
			}
			
			$group = new XMLElement('fieldset');
			$group->setAttribute('class', 'settings');
			$group->appendChild(new XMLElement('legend', __('Dump Database')));			
			

			$div = new XMLElement('div', NULL, array('id' => 'dump-actions', 'class' => 'label'));	
			
			$disabled = (count($filesWriteable) < 2 && !$downloadMode ? array('disabled' => 'disabled') : array());
			
			$span = new XMLElement('span', NULL, array('class' => 'frame'));
			$span->appendChild(new XMLElement('button', __('Save Authors'), array_merge(array('name' => 'action[dump][authors]', 'type' => 'submit'), $disabled)));
			$span->appendChild(new XMLElement('button', __('Save Structure'), array_merge(array('name' => 'action[dump][structure]', 'type' => 'submit'), $disabled)));
			$span->appendChild(new XMLElement('button', __('Save Data'), array_merge(array('name' => 'action[dump][data]', 'type' => 'submit'), $disabled)));
			$div->appendChild($span);
			
			
			if($downloadMode)
				$div->appendChild(new XMLElement('p', __('Dumping is set to <code>%s</code>. Your dump will be downloaded and won\'t touch local dumps on the server.',array(Symphony::Configuration()->get('dump', 'dump_db'))), array('class' => 'help')));
			
			$disabled = (Symphony::Configuration()->get('restore', 'dump_db') === 'yes' ? array() : array('disabled' => 'disabled'));
			
			$span = new XMLElement('span', NULL, array('class' => 'frame'));
			$span->appendChild(new XMLElement('button', __('Restore Authors'), array_merge(array('name' => 'action[restore][authors]', 'type' => 'submit'), $disabled)));
			$span->appendChild(new XMLElement('button', __('Restore Structure'), array_merge(array('name' => 'action[restore][structure]', 'type' => 'submit'), $disabled)));
			$span->appendChild(new XMLElement('button', __('Restore Data'), array_merge(array('name' => 'action[restore][data]', 'type' => 'submit'), $disabled)));
			$div->appendChild($span);
			
			if(Symphony::Configuration()->get('restore', 'dump_db') !== 'yes') {
				$div->appendChild(new XMLElement('p', __('Restoring needs to be enabled in <code>/manifest/config.php</code>.',array($this->path, $filename)), array('class' => 'help')));
			}

			$group->appendChild($div);						
			$context['wrapper']->appendChild($group);
						
		}
		
		private function __filesWriteable() {
			$return = array();
			
			foreach(array("data", "authors") AS $mode) {
				$filename = DOCROOT . $this->path . '/' . $this->generateFilename($mode);
				
				if(!file_exists($filename) || is_writable($filename)) { // file doesn't exist or is writeable
					$return[] = $mode;
				}
			}
			
			if($return == array())
				$return = NULL;
			
			return $return;
		}
		
		private function __downloadMode() {
			return in_array(Symphony::Configuration()->get('dump', 'dump_db'), array('text','download'));
		}
		
		private function __filesNewer() {	
			$return = array();
					
			$last_sync = strtotime(Symphony::Configuration()->get('last_sync', 'dump_db'));
			
			if($last_sync === FALSE)
				return FALSE;
			
			foreach(array("data", "authors") AS $mode) {
				$filename = DOCROOT . $this->path . '/' . $this->generateFilename($mode);
				
				if(file_exists($filename) && $last_sync < filemtime($filename)) { // file exists and is newer than $last_sync
					$return[] = $mode;
				}
			}
				
			if($return == array())
				$return = NULL;

			return $return;
		}
		
		private function __restore($context){
			if(Symphony::Configuration()->get('restore', 'dump_db') !== 'yes')  // make sure the user knows what he's doing
				return;
			
			require_once(dirname(__FILE__) . '/lib/class.mysqlrestore.php');
			
			$restore = new MySQLRestore(Symphony::Database());
			
			$mode = NULL;
			if(isset($_POST['action']['restore']['authors'])) {
				$mode = 'authors';
			}
			elseif(isset($_POST['action']['restore']['structure'])) {
				$mode = 'structure';
			}
			else $mode = 'data';
			if($mode == NULL) return;
			
			$filename = $this->generateFilename($mode);
			
			$return = $restore->import(file_get_contents(DOCROOT . $this->path . '/' . $filename));
			
			if(FALSE !== $return) {
				Administration::instance()->Page->pageAlert(__('%s successfully restored from <code>%s/%s</code> in %d queries.',array(__(ucfirst($mode)),$this->path,$filename,$return)), Alert::SUCCESS);
				Symphony::Configuration()->set('last_sync', date('c') ,'dump_db');
				Administration::instance()->saveConfig();
			}
			else {
				Administration::instance()->Page->pageAlert(__('An error occurred while trying to import from <code>%s/%s</code>.',array($this->path,$filename)), Alert::ERROR);
			}
		}
		
		private function __dump($context){
			$sql_schema = $sql_data = NULL;
			
			require_once(dirname(__FILE__) . '/lib/class.mysqldump.php');
			
			$dump = new MySQLDump(Symphony::Database());
			
			$rows = Symphony::Database()->fetch("SHOW TABLES LIKE 'tbl_%';");
			$rows = array_map (create_function ('$x', 'return array_values ($x);'), $rows);
			$tables = array_map (create_function ('$x', 'return $x[0];'), $rows);
			
			$mode = NULL;
			if(isset($_POST['action']['dump']['authors'])) {
				$mode = 'authors';
			}
			elseif(isset($_POST['action']['dump']['structure'])) {
				$mode = 'structure';
			}
			else $mode = 'data';
			
			if($mode == NULL) return;
			
			$filename = $this->generateFilename($mode);
			
			## Find table prefix used for this install of Symphony
			$tbl_prefix = Symphony::Configuration()->get('tbl_prefix', 'database');

			foreach ($tables as $table){
				$table = str_replace($tbl_prefix, 'tbl_', $table);
				
				if($mode == 'authors') {
					switch($table) {
						case 'tbl_authors':
						case 'tbl_forgotpass':
							$sql_data .= $dump->export($table, MySQLDump::ALL);
							break;
						case 'tbl_sessions':
							$sql_data .= $dump->export($table, MySQLDump::STRUCTURE_ONLY);	
							break;
						default: // ignore everything but the authors
							break;
					}
				}
				if($mode == 'structure') {

					## Create arrays of tables to dump
					$db_tables = $this->__getDatabaseTables($tbl_prefix);
					$structural_data_tables = $this->__getStructuralDataTables($db_tables);
					$sql_data = $this->__dumpStructuralData($dump, $structural_data_tables);

				}
				elseif($mode == 'data') {
					switch($table) {
						case 'tbl_authors': // ignore authors
						case 'tbl_forgotpass':
						case 'tbl_sessions':
							break;
						case 'tbl_cache':
							$sql_data .= $dump->export($table, MySQLDump::STRUCTURE_ONLY);
							break;
						default:
							$sql_data .= $dump->export($table, MySQLDump::ALL);
					}
				}
				
			}
			
			if(Symphony::Configuration()->get('dump', 'dump_db') === 'download') {
				header("Pragma: public");
				header("Expires: 0");
				header("Cache-Control: must-revalidate, post-check=0, pre-check=0");

				header("Content-Type: application/octet-stream");
				header("Content-Transfer-Encoding: binary");
				header("Content-Disposition: attachment; filename=" . $mode . ".sql");
				echo $sql_data;
				die();
			}
			elseif(Symphony::Configuration()->get('dump', 'dump_db') === 'text') {
				header("Pragma: public");
				header("Expires: 0");
				header("Cache-Control: must-revalidate, post-check=0, pre-check=0");

				header("Content-Type: text/plain; charset=UTF-8");
				echo $sql_data;
				die();
			}
			else {
				if(FALSE !== @file_put_contents(DOCROOT . $this->path . '/' . $filename, $sql_data)) {
					Administration::instance()->Page->pageAlert(__('%s successfully dumped into <code>%s/%s</code>.',array(__(ucfirst($mode)),$this->path,$filename)), Alert::SUCCESS);
					Symphony::Configuration()->set('last_sync', date('c') ,'dump_db');
					Administration::instance()->saveConfig();
				}
				else {
					Administration::instance()->Page->pageAlert(__('An error occurred while trying to write <code>%s/%s</code>.',array($this->path,$filename)), Alert::ERROR);
				}
			}
			
		}
		
		private function generateFilename($mode) {
			return sprintf($this->format, $mode);
		}

		private function __getDatabaseTables($tbl_prefix){

			## Find all tables in the database
			$Database = Symphony::Database();
			$all_tables = $Database->fetch('show tables');

			## Find length of prefix to test for table prefix
			$prefix_length = strlen($tbl_prefix);

			## Flatten multidimensional tables array
			$db_tables = array();
			foreach($all_tables as $table){
				$value = array_values($table);
				$value = $value[0];

				## Limit array of tables to those using the table prefix
				## and replace the table prefix with tbl
				if(substr($value, 0, $prefix_length) === $tbl_prefix){
					$db_tables[] = 'tbl_' . substr($value, $prefix_length);
				}
			}

			return $db_tables;

		}

		private function __getStructureTables($structure_tables){

			## Create array of tables to ignore for structure-only dump
			$ignore_tables = array(
				'tbl_entries_',
				'tbl_fields_'
			);

			## Remove tables from list for structure-only dump
			foreach($structure_tables as $index => $table){
				foreach($ignore_tables as $starts){
					if(substr($table, 0, strlen($starts)) === $starts ){
						unset($structure_tables[$index]);
					}
				}
			}

			## Add fields tables back into list
			$structure_tables[] = 'tbl_fields_%';
			sort($structure_tables);

			return $structure_tables;

		}

		private function __getDataTables($data_tables){

			## Create array of tables to ignore for data-only dump
			$ignore_tables = array(
				'tbl_authors',
				'tbl_cache',
				'tbl_entries_',
				'tbl_fields_',
				'tbl_forgotpass',
				'tbl_sessions'
			);

			## Remove tables from list for data-only dump
			foreach($data_tables as $index => $table){
				foreach($ignore_tables as $starts){
					if(substr($table, 0, strlen($starts)) === $starts ){
						unset($data_tables[$index]);
					}
				}
			}

			return $data_tables;

		}

		private function __getStructuralDataTables($tables){

			## Create array of tables to ignore for structural data dump
			$ignore_tables = array(
				'tbl_authors',
				'tbl_cache',
				'tbl_entries',
				'tbl_forgotpass',
				'tbl_sessions'
			);

			## Remove tables from list for structural data dump
			foreach($tables as $index => $table){
				foreach($ignore_tables as $starts){
					if(substr($table, 0, strlen($starts)) === $starts ){
						unset($tables[$index]);
					}
				}
			}

			return $tables;

		}

		private function __dumpSchema($dump, $structure_tables, $tbl_prefix = NULL){

			## Create variables for the dump files
			$sql_schema = NULL;

			## Grab the schema
			foreach($structure_tables as $t) $sql_schema .= $dump->export($t, MySQLDump::STRUCTURE_ONLY);

			if($tbl_prefix !== NULL) {
				$sql_schema = str_replace('`' . $tbl_prefix, '`tbl_', $sql_schema);
			}

			$sql_schema = preg_replace('/AUTO_INCREMENT=\d+/i', NULL, $sql_schema);

			return $sql_schema;

		}

		private function __dumpData($dump, $data_tables, $tbl_prefix = NULL){

			## Create variables for the dump files
			$sql_data = NULL;

			## Field data and entry data schemas needs to be apart of the workspace sql dump
			$sql_data  = $dump->export('tbl_fields_%', MySQLDump::ALL);
			$sql_data .= $dump->export('tbl_entries_%', MySQLDump::ALL);

			## Grab the data
			foreach($data_tables as $t){
				$sql_data .= $dump->export($t, MySQLDump::DATA_ONLY);
			}

			if($tbl_prefix !== NULL) {
				$sql_schema = str_replace('`' . $tbl_prefix, '`tbl_', $sql_schema);
			}

			return $sql_data;

		}

		private function __dumpStructuralData($dump, $data_tables, $tbl_prefix = NULL){

			## Create variables for the dump files
			$sql_data = NULL;

			## Grab the data
			foreach($data_tables as $t){
				$sql_data .= $dump->export($t, MySQLDump::ALL);
			}

			if($tbl_prefix !== NULL) {
				$sql_schema = str_replace('`' . $tbl_prefix, '`tbl_', $sql_schema);
			}

			return $sql_data;

		}

	}
