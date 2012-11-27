<?

function dtime($func, $args = null, $name = 'default_func_timer')
{
	return LDDevel_Class::create()->time($func, $args, $name);
}

function dtick($name = 'default_timer', $callback = null)
{
	return LDDevel_Class::create()->tick($name, $callback);
}

function dmem($func, $args = null, $name = 'default_func_memory')
{
	return LDDevel_Class::create()->memory($func, $args, $name);
}

function dmemtick($name = 'default_memory', $callback = null)
{
	return LDDevel_Class::create()->memory_tick($name, $callback);
}

function dlog($message, $params = array())
{
	return LDDevel_Class::create()->log($message, $params);
}

function ddump($var, $params = array())
{
	return LDDevel_Class::create()->dump($var, $params);
}

function dsqlformat($sql, $params = array())
{
	return LDDevel_Class::create()->sql_format($sql, $params);
}

function dexit()
{
	return LDDevel_Class::create()->close();
}