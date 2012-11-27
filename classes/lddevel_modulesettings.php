<?

	class LDDevel_ModuleSettings extends Core_ModuleSettings
	{
		public static function create($module_id, $form_code)
		{
			$record_code = self::get_record_code($module_id, $form_code);
			if (array_key_exists($record_code, self::$loaded_objects))
				return self::$loaded_objects[$record_code];

			$obj = new self();
			return $obj->get($module_id, $form_code);
		}

		public function get($module_id, $form_code)
		{
			$record_code = self::get_record_code($module_id, $form_code);
			Db_ActiveRecord::disable_column_cache();

			$obj = $this->find_by_record_code($record_code);
			if (!$obj)
			{
				$class_name = get_class($this);
				$obj = new $class_name();
			}

			$obj->module_id = $module_id;
			$obj->form_code = $form_code;
			$obj->record_code = $record_code;
			
			$obj->define_form_fields();
			
			self::$loaded_objects[$record_code] = $obj;
			
			return $obj;
		}

		public function get_field_types()
		{
			return $this->custom_columns;
		}

		protected static function get_record_code($module_id, $form_code)
		{
			return $module_id.'-'.$form_code;
		}

		public function after_save()
		{
			//$this->get_module_obj()->afterSaveSettingsData($this, $this->form_code);
		}
	}

?>