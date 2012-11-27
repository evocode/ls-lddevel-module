<?php

class LDDevel_Module extends Core_ModuleBase
{
	public static $engine = null;

	/*
	 * Module information
	 */
	protected function createModuleInfo()
	{
		return new Core_ModuleInfo
		(
			"Development Tools",
			"Adds development information to the footer",
			"Lemonoid",
			"http://lemonoid.com"
		);
	}

	/*
	 * Add events
	 */
	public function subscribeEvents()
	{
		$continue = Phpr::$config->get('ENABLE_DEVELOPER_TOOLS', false);
		if( !$continue )
			return;

		self::$engine = LDDevel_Class::create();

		Backend::$events->addEvent('core:onInitialize', self::$engine, 'core_initialize');

		Backend::$events->addEvent('core:onBeforeDatabaseQuery', self::$engine, 'on_before_query');
		Backend::$events->addEvent('core:onAfterDatabaseQuery', self::$engine, 'on_after_query');
	}

	/*
	 * Setup setting fields
	 */
	public function buildSettingsForm($model, $form_code)
	{
		$model->add_field('start_collapsed', 'Start Collapsed', 'full', db_bool)->tab('Options');
		$model->add_field('no_ajax', 'No AJAX', 'full', db_bool)->tab('Options');
		$model->add_field('no_backend', 'No Backend', 'full', db_bool)->tab('Options');
		$model->add_field('only_loggedin', 'Only Visible to Logged in Admins', 'full', db_bool)->tab('Options');

		$model->add_field('sql_format', 'Format SQL', 'full', db_bool)->tab('SQL');

		$model->add_field('var_get', 'GET Data', 'full', db_bool)->tab('Variables');
		$model->add_field('var_post', 'POST Data', 'full', db_bool)->tab('Variables');
		$model->add_field('var_session', 'SESSION Data', 'full', db_bool)->tab('Variables');
		$model->add_field('var_cookie', 'COOKIE Data', 'full', db_bool)->tab('Variables');
		$model->add_field('var_lsconfig', 'LemonStand Config Data', 'full', db_bool)->tab('Variables');
		$model->add_field('var_headers', 'Headers', 'full', db_bool)->tab('Variables');
		$model->add_field('var_constants', 'Defined Constants', 'full', db_bool)->tab('Variables');
		$model->add_field('var_functions', 'Defined Functions', 'full', db_bool)->tab('Variables');
		$model->add_field('var_includes', 'Include Files', 'full', db_bool)->tab('Variables');
		$model->add_field('var_interfaces', 'Declared Interfaces', 'full', db_bool)->tab('Variables');
		$model->add_field('var_classes', 'Declared Classes', 'full', db_bool)->tab('Variables');
	}

	/*
	 * Initial values of setting fields
	 */
	public function initSettingsData($model, $form_code)
	{
		$model->start_collapsed = 0;
		$model->no_ajax = 0;
		$model->no_backend = 0;
		$model->only_loggedin = 0;

		$model->sql_format = 0;

		$model->var_get = 1;
		$model->var_post = 1;
		$model->var_session = 1;
		$model->var_cookie = 1;
		$model->var_lsconfig = 1;
		$model->var_headers = 1;
		$model->var_constants = 1;
		$model->var_functions = 1;
		$model->var_includes = 1;
		$model->var_interfaces = 1;
		$model->var_classes = 1;
	}
}
