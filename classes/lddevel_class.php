<?

class LDDevel_Class
{
	/*
	 * Instance of this class
	 */
	protected static $instance = null;

	/*
	 * Data store for class
	 */
	protected $data = array(
		'queries' => array(),
		'logs' => array(),
		'timers' => array(),
		'memory' => array()
	);

	/*
	 * Current active query key
	 */
	protected $active_query_key = null;

	/*
	 * Array of classes used in dump() function
	 */
	private $_dump_objects = array();

	/*
	 * Settings for this module
	 */
	private $options = array();

	/*
	 * Create instance of this class
	 */
	public static function create()
	{
		if(self::$instance)
			return self::$instance;

		return self::$instance = new self();
	}

	/*
	 * Start timers on the first event
	 */
	public function core_initialize()
	{
		$this->data['init_time'] = microtime(true);
		$this->data['init_memory'] = memory_get_usage(true);
		$this->data['init_memory_peak'] = memory_get_peak_usage(true);

		ob_start( array(self::$instance, 'handle_buffer') );
	}

	/*
	 * Process the before query event
	 */
	public function on_before_query($sql)
	{
		$this->active_query_key = uniqid();
		$this->data['queries'][$this->active_query_key] = array('sql'=>$sql, 'start' => microtime(true), 'end' => null);
	}

	/*
	 * Process the after query event
	 */
	public function on_after_query($sql, $results)
	{
		if (isset($this->data['queries'][$this->active_query_key]))
		{
			$this->data['queries'][$this->active_query_key]['end'] = microtime(true);
		}
	}

	/*
	 * Handle the output buffer
	 */
	public function handle_buffer($buffer)
	{
		//Stop execution
		if (defined('NO_LDDEVEL') && NO_LDDEVEL)
			return $buffer;

		if (!$this->options)
			$this->load_options();

		//Instance
		$instance = Cms_Controller::get_instance();
		$is_ajax = $instance ? $instance->ajax_mode : false;

		//Check for event handler
		if (isset($_SERVER['HTTP_PHPR_EVENT_HANDLER']) && Phpr_Controller::$current !== null)
		{
			//test for ping lock
			$pingback = Phpr_Controller::$current->eventHandler('onPingLock');
			if (isset($pingback['handler']) && $pingback['handler'] == $_SERVER['HTTP_PHPR_EVENT_HANDLER'])
				return $buffer;

			//event handlers should be treated like ajax requests
			$is_ajax = true;

			/*
			//test for any event handler
			$eventhanler = false;
			foreach ( $_POST as $postKey=>$postValue )
			{
				$matches = null;
				$keyParts = explode("|", $postKey);

				foreach($keyParts as $keyPart)
				{
					if ( preg_match("/^".$this->_EventPostPrefix."\{(?P<handler>On[a-zA-Z_]*)\}$/i", $keyPart, $matches) )
					{
						//$this->_ExecEventHandler($matches["handler"]);
						return $buffer;
					}
				}
			}
			*/
		}

		//check for chart data request
		if (Phpr::$router->action == 'chart_data')
			return $buffer;
		
		//Check if no ajax option is on
		$no_ajax = $this->get_option('no_ajax');
		if ($no_ajax && $is_ajax)
			return $buffer;

		//Check if no backend option is on
		$is_backend = $this->is_backend();
		$no_backend = $this->get_option('no_backend');
		if ($no_backend && $is_backend)
			return $buffer;

		//Check if it should only be visible to admins
		$only_admins = $this->get_option('only_loggedin');
		if ($only_admins)
		{
			$user = Phpr::$security->getUser();
			if (!$user)
				return $buffer;
		}

		//Unique page id
		$page_id = uniqid();

		//Current times
		$timeZoneObj = new DateTimeZone( Phpr::$config->get('TIMEZONE') );
		$timeObj = new Phpr_DateTime( null, $timeZoneObj );

		//Set end time
		$this->data['end_time'] = microtime(true);
		$this->data['end_memory'] = memory_get_usage(true);
		$this->data['end_memory_peak'] = memory_get_peak_usage(true);
		
		//Process timers
		$this->data['timers']['_completed'] = array();
		$this->data['timers']['_completed']['name'] = 'Page Completed';
		$this->data['timers']['_completed']['start'] = $this->data['init_time'];
		$this->data['timers']['_completed']['end'] = $this->data['end_time'];
		$this->data['timers']['_completed']['time'] = $this->data['end_time'] - $this->data['init_time'];

		$page_load_time = $this->data['timers']['_completed']['time'];

		//Total queries
		$total_queries = count($this->data['queries']);

		//Process queries
		$total_query_time = 0;

		foreach($this->data['queries'] as $k=>$query)
		{
			$total = $query['end'] - $query['start'];
			$this->data['queries'][$k]['time'] = round($total, 4);

			$total_query_time += $total;
		}

		$total_query_time = round($total_query_time, 4);
		$average_query = $total_queries > 0 ? $total_query_time / $total_queries : 0;

		//Process memory usage
		$this->data['memory']['_completed'] = array(); 
		$this->data['memory']['_completed']['name'] = 'Page Completed';
		$this->data['memory']['_completed']['memory'] = $this->data['end_memory'] - $this->data['init_memory'];
		$this->data['memory']['_completed']['memory_peak'] = $this->data['end_memory_peak'] - $this->data['init_memory_peak'];

		$total_memory = $this->data['memory']['_completed']['memory'];
		$total_peak_memory = $this->data['memory']['_completed']['memory_peak'];

		//Process log items
		$total_logs = count($this->data['logs']);

		//Start output
		$output = '';

		//if this is not ajax request, output devel info
		if (!$is_ajax)
		{
			if ($is_backend)
				$js_request = 'onclick="$(this).getForm().sendPhprRemote(\'' . url('lddevel/settings') . '\', \'onSaveDevelConfig\', {loadIndicator: {show: false}})"';
			else
				$js_request = 'onclick="$(this).getForm().sendRequest(\'lddevel:on_saveConfig\', {})"';

			$output .= '
			<!-- LDDEVEL: START TOOLBAR -->
			<style type="text/css">
			';

			$output .= file_get_contents(dirname(dirname(__FILE__)) . '/resources/css/devel.css');

			$output .= '
			</style>
			<div class="lddevel">
				<div class="lddevel-window">
					<div class="lddevel-content-menu">
						<ul id="lddevel-open-requests" class="lddevel-requests">
						</ul>
					</div>
					<div class="lddevel-content-container">
						<div id="lddevel-request-options" class="lddevel-content-area lddevel-form" style="display:none">
							<form action="" method="post">
								<fieldset>
		    						<legend>Options</legend>

		    						<div class="checkbox-block">
										<label>
											<input type="checkbox" name="LDDevel_ModuleSettings[start_collapsed]" value="1" ' . $this->checkbox_option('start_collapsed') . $js_request . '> Start Collapsed
										</label>
									</div>

									<div class="checkbox-block">
										<label>
											<input type="checkbox" name="LDDevel_ModuleSettings[no_ajax]" value="1" ' . $this->checkbox_option('no_ajax') . $js_request . '> Do Not Monitor AJAX Requests
										</label>
									</div>

									<div class="checkbox-block">
										<label>
											<input type="checkbox" name="LDDevel_ModuleSettings[no_backend]" value="1" ' . $this->checkbox_option('no_backend') . $js_request . '> Disable in Admin
										</label>
									</div>

									<div class="checkbox-block">
										<label>
											<input type="checkbox" name="LDDevel_ModuleSettings[only_loggedin]" value="1" ' . $this->checkbox_option('only_loggedin') . $js_request . '> Only Visible to Logged in Admins
										</label>
									</div>
		    					</fieldset>

								<fieldset>
		    						<legend>SQL</legend>

		    						<div class="checkbox-block">
										<label>
											<input type="checkbox" name="LDDevel_ModuleSettings[sql_format]" value="1" ' . $this->checkbox_option('sql_format') . $js_request . '> Format SQL
										</label>
									</div>
		    					</fieldset>

								<fieldset>
		    						<legend>Variables</legend>

		    						<div class="checkbox-block">
										<label>
											<input type="checkbox" name="LDDevel_ModuleSettings[var_get]" value="1" ' . $this->checkbox_option('var_get') . $js_request . '> GET Data
										</label>
									</div>

									<div class="checkbox-block">
										<label>
											<input type="checkbox" name="LDDevel_ModuleSettings[var_post]" value="1" ' . $this->checkbox_option('var_post') . $js_request . '> POST Data
										</label>
									</div>

									<div class="checkbox-block">
										<label>
											<input type="checkbox" name="LDDevel_ModuleSettings[var_session]" value="1" ' . $this->checkbox_option('var_session') . $js_request . '> SESSION Data
										</label>
									</div>

									<div class="checkbox-block">
										<label>
											<input type="checkbox" name="LDDevel_ModuleSettings[var_cookie]" value="1" ' . $this->checkbox_option('var_cookie') . $js_request . '> COOKIE Data
										</label>
									</div>

									<div class="checkbox-block">
										<label>
											<input type="checkbox" name="LDDevel_ModuleSettings[var_lsconfig]" value="1" ' . $this->checkbox_option('var_lsconfig') . $js_request . '> LemonStand Config
										</label>
									</div>

									<div class="checkbox-block">
										<label>
											<input type="checkbox" name="LDDevel_ModuleSettings[var_headers]" value="1" ' . $this->checkbox_option('var_headers') . $js_request . '> Headers
										</label>
									</div>

									<div class="checkbox-block">
										<label>
											<input type="checkbox" name="LDDevel_ModuleSettings[var_constants]" value="1" ' . $this->checkbox_option('var_constants') . $js_request . '> Defined Constants
										</label>
									</div>

									<div class="checkbox-block">
										<label>
											<input type="checkbox" name="LDDevel_ModuleSettings[var_functions]" value="1" ' . $this->checkbox_option('var_functions') . $js_request . '> Defined Functions
										</label>
									</div>

									<div class="checkbox-block">
										<label>
											<input type="checkbox" name="LDDevel_ModuleSettings[var_includes]" value="1" ' . $this->checkbox_option('var_includes') . $js_request . '> Include Files
										</label>
									</div>

									<div class="checkbox-block">
										<label>
											<input type="checkbox" name="LDDevel_ModuleSettings[var_interfaces]" value="1" ' . $this->checkbox_option('var_interfaces') . $js_request . '> Declared Interfaces
										</label>
									</div>

									<div class="checkbox-block">
										<label>
											<input type="checkbox" name="LDDevel_ModuleSettings[var_classes]" value="1" ' . $this->checkbox_option('var_classes') . $js_request . '> Declared Classes
										</label>
									</div>
								</fieldset>
							</form>
						</div>
					</div>
				</div>
				';

				$output .= '
				<ul id="lddevel-open-tabs" class="lddevel-tabs">
					<li><a class="lddevel-tab" data-lddevel-tab="lddevel-timers">Time <span id="lddevel-count-time" class="lddevel-count">' . round($page_load_time, 4) . 'ms</span></a></li>
					<li>
						<a data-lddevel-tab="lddevel-sql" class="lddevel-tab" href="#">SQL 
							<span id="lddevel-count-query" class="lddevel-count">' . $total_queries . '</span>
							<span id="lddevel-count-querytime" class="lddevel-count">' . $total_query_time . 'ms</span>
						</a>
					</li>
					<li><a data-lddevel-tab="lddevel-memory" class="lddevel-tab">Memory <span id="lddevel-count-mem" class="lddevel-count">' . $this->get_file_size(round($total_memory, 4)) . ' (' . $this->get_file_size(round($total_peak_memory, 4)) . ')</span></a></li>
					<li><a data-lddevel-tab="lddevel-log" class="lddevel-tab" href="#">Log <span id="lddevel-count-log" class="lddevel-count">' . $total_logs . '</span></a></li>
					<li><a data-lddevel-tab="lddevel-var" class="lddevel-tab" href="#">Variables</a></li>
					<li class="lddevel-tab-right"><a id="lddevel-hide" href="#">&#8614;</a></li>
					<li class="lddevel-tab-right"><a id="lddevel-config" href="#">&oplus;</a></li>
					<li class="lddevel-tab-right"><a id="lddevel-close" href="#">&times;</a></li>
					<li class="lddevel-tab-right"><a id="lddevel-zoom" href="#">&#8645;</a></li>
				</ul>

				<ul id="lddevel-closed-tabs" class="lddevel-tabs">
					<li><a id="lddevel-show" href="#">&#8612;</a></li>
				</ul>
			</div>
			';

			$output .= '
			<script>window.jQuery || document.write("<script src=\'//ajax.googleapis.com/ajax/libs/jquery/1.8.3/jquery.min.js\'>\x3C/script>")</script>
			<script type="text/javascript">
			';

			$output .= file_get_contents(dirname(dirname(__FILE__)) . '/resources/javascript/devel.js');

			$output .= "\n\n";

			//Add remote request for admin
			if ($is_backend)
			{
				$output .= '
				//Remote request
				Element.implement({
				    sendPhprRemote: function(action, handlerName, options)
				    {   
				        var defaultOptions = {url: action, handler: handlerName, loadIndicator: {element: this}};
				        new Request.Phpr($merge(defaultOptions, options)).post(this);
				        return false;
				    }
				});
				';
			}

			$output .= '
			</script>
			<!-- LDDEVEL: END TOOLBAR -->
			';
		}

		//Start javascript update
		$output .= '<script type="text/javascript"> jQuery(document).ready(function($) { ' . "\n";

		//Set config values to pass through JS
		$config = array();

		$collapsed = $this->get_option('start_collapsed');
		if ($collapsed)
			$config['collapsed'] = 'true';

		//output config
		if ($config)
		{
			$output .= 'lddevel.set_config({';

			foreach ($config as $k=>$c)
			{
				$output .= $k . ': ' . $c . ', ';
			}

			$output = substr($output, 0, -2);

			$output .= '});' . "\n";
		}


		//Output stats
		$output .= 'lddevel.add_request(\'' . $page_id . '\', {name: ' . $this->safe_parameter($timeObj->format('%H:%M:%S')) . ', log: ' . $total_logs . ', sql: ' . $total_queries . ', sqltime: ' . $total_query_time . ', time: ' . round($page_load_time, 4) . ', memory: ' . $this->safe_parameter($this->get_file_size(round($total_memory, 4))) . ', memorypeak: ' . $this->safe_parameter($this->get_file_size(round($total_peak_memory))) . '});' . "\n";


		//Output time
		$output .= 'lddevel.add_timers(\'' . $page_id . '\', [' . "\n";

		$rid = 0;
		foreach ($this->data['timers'] as $key=>$time)
		{
			if (isset($time['ticks']))
			{
				$tc = 0;
				foreach ($time['ticks'] as $tickname=>$tick)
				{
					$name = isset($time['name']) ? $time['name'] : $key;
					$output .= '{name: ' . $this->safe_parameter($name) . ', time: ' . round($tick['time'], 4) . ', diff: ' . round($tick['diff'], 4) . ', tick: ' . ($tc + 1) . '}, ' . "\n";
			
					$tc++;
				}
			}
			else
			{
				$name = isset($time['name']) ? $time['name'] : $key;
				$output .= '{name: ' . $this->safe_parameter($name) . ', time: ' . round($time['time'], 4) . ', diff: ' . (isset($time['diff']) ? round($time['diff'], 4) : 'null') . ', tick: null}, ' . "\n";
			}
			$rid++;
		}

		if ($rid > 0)
			$output = substr(trim($output, "\n"), 0, -2);

		$output .= "\n" . ']);' . "\n";


		//Output queries
		$output .= 'lddevel.add_queries(\'' . $page_id . '\', [' . "\n";
		$rid = 0;
		$priority = 1;

		foreach ($this->data['queries'] as $k=>$query)
		{
			$priority = 1;

			if ($query['time'] > $average_query)
				$priority = 2;

			if ($query['time'] > 1)
				$priority = 3;

			$nice_sql = $this->get_option('sql_format');
			if ($nice_sql)
				$sql = $this->encode_parameter($this->sql_format($query['sql']));
			else
				$sql = $this->safe_parameter($query['sql']);

			$output .= '{ id: ' . ($rid+1) . ', sql: ' . $sql . ', time: ' . $query['time'] . ', priority: ' . $priority . ' }, ' . "\n";
			$rid++;
		}

		if ($rid > 0)
			$output = substr(trim($output, "\n"), 0, -2);

		$output .= "\n" . ']);' . "\n";


		//Output memory
		$output .= 'lddevel.add_memory(\'' . $page_id . '\', [' . "\n";

		$rid = 0;
		foreach ($this->data['memory'] as $key=>$mem)
		{
			if (isset($mem['ticks']))
			{
				$tc = 0;
				foreach ($mem['ticks'] as $tickname=>$tick)
				{
					$name = isset($mem['name']) ? $mem['name'] : $key;
					$output .= '{name: ' . $this->safe_parameter($name) . ', memory: ' . $this->safe_parameter($this->get_file_size(round($tick['memory'], 4))) . ', memorypeak: ' . $this->safe_parameter($this->get_file_size(round($tick['memory_peak'], 4))) . ', diff: ' . (isset($tick['diff']) ? $this->safe_parameter($this->get_file_size(round($tick['diff'], 4))) : 'null') . ', diffpeak: ' . (isset($tick['diff_peak']) ? $this->safe_parameter($this->get_file_size(round($tick['diff_peak'], 4))) : 'null') . ', tick: ' . ($tc + 1)  . '}, ' . "\n";
			
					$tc++;
				}
			}
			else
			{
				$name = isset($mem['name']) ? $mem['name'] : $key;
				$output .= '{name: ' . $this->safe_parameter($name) . ', memory: ' . $this->safe_parameter($this->get_file_size(round($mem['memory'], 4))) . ', memorypeak: ' . $this->safe_parameter($this->get_file_size(round($mem['memory_peak'], 4))) . ', diff: ' . (isset($mem['diff']) ? $this->safe_parameter($this->get_file_size(round($mem['diff'], 4))) : 'null') . ', diffpeak: ' . (isset($mem['diff_peak']) ? $this->safe_parameter($this->get_file_size(round($mem['diff_peak'], 4))) : 'null') . ', tick: null}, ' . "\n";
			}
			$rid++;
		}

		if ($rid > 0)
			$output = substr(trim($output, "\n"), 0, -2);

		$output .= "\n" . ']);' . "\n";


		//output log
		$output .= 'lddevel.add_logs(\'' . $page_id . '\', [' . "\n";
		$rid = 0;
		foreach($this->data['logs'] as $log)
		{
			$class = $rid % 2 == 0 ? 'row-odd' : 'row-even';

			$output .= '{ type: ' . $this->safe_parameter($log[1]) . ', message: ' . $this->encode_parameter(htmlspecialchars($log[0])) . ' }, ' . "\n";
			$rid++;
		}

		if ($rid > 0)
			$output = substr(trim($output, "\n"), 0, -2);

		$output .= "\n" . ']);' . "\n";


		//output variables
		$output .= 'lddevel.add_variables(\'' . $page_id . '\', [' . "\n";
		$rid = 0;

		$get_fields = $this->get_option('var_get');
		if ($get_fields)
		{
			$output .= '{type: \'get\', value: ';

			$output .= $this->recursive_print(array('encode' => 'json', 'varname' => 'Phpr::$request->get_fields', 'value' => Phpr::$request->get_fields, 'sep' => "}, \n{type: 'get', value: "));

			$output .= '}, ' . "\n";

			$rid++;
		}

		$post_fields = $this->get_option('var_post');
		if ($post_fields)
		{
			$output .= '{type: \'post\', value: ';

			$output .= $this->recursive_print(array('encode' => 'json', 'varname' => '$_POST', 'value' => $_POST, 'sep' => "}, \n{type: 'post', value: "));

			$output .= '}, ' . "\n";

			$rid++;
		}

		$session_fields = $this->get_option('var_session');
		if ($session_fields)
		{
			$output .= '{type: \'session\', value: ';

			$output .= $this->recursive_print(array('encode' => 'json', 'varname' => '$_SESSION', 'value' => $_SESSION, 'sep' => "}, \n{type: 'session', value: "));

			$output .= '}, ' . "\n";

			$rid++;
		}

		$cookie_fields = $this->get_option('var_cookie');
		if ($cookie_fields)
		{
			$output .= '{type: \'cookie\', value: ';

			$output .= $this->recursive_print(array('encode' => 'json', 'varname' => '$_COOKIE', 'value' => $_COOKIE, 'sep' => "}, \n{type: 'cookie', value: "));

			$output .= '}, ' . "\n";

			$rid++;
		}

		$lsconfig_fields = $this->get_option('var_lsconfig');
		if ($lsconfig_fields)
		{
			global $CONFIG;

			$output .= '{type: \'lsconfig\', value: ';

			$output .= $this->recursive_print(array('encode' => 'json', 'varname' => '$CONFIG', 'value' => $CONFIG, 'sep' => "}, \n{type: 'lsconfig', value: "));

			$output .= '}, ' . "\n";

			$rid++;
		}

		$header_fields = $this->get_option('var_headers');
		if ($header_fields)
		{
			$headers = array();
			foreach ($_SERVER as $name => $value) 
			{ 
				if (substr($name, 0, 5) == 'HTTP_') 
				{ 
					$name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5))))); 
					$headers[$name] = $value; 
				}
				else if ($name == "CONTENT_TYPE")
				{ 
					$headers["Content-Type"] = $value; 
				}
				else if ($name == "CONTENT_LENGTH")
				{ 
					$headers["Content-Length"] = $value; 
				}
			}

			$output .= '{type: \'header\', value: ';

			$output .= $this->recursive_print(array('encode' => 'json', 'value' => $headers, 'escape_key' => false, 'sep' => "}, \n{type: 'header', value: "));

			$output .= '}, ' . "\n";

			$rid++;
		}

		$constant_fields = $this->get_option('var_constants');
		if ($constant_fields)
		{
			$constants = get_defined_constants(true);

			$output .= '{type: \'constant\', value: ';

			$output .= $this->recursive_print(array('encode' => 'json', 'value' => $constants['user'], 'escape_key' => false, 'sep' => "}, \n{type: 'constant', value: "));

			$output .= '}, ' . "\n";

			$rid++;
		}

		$function_fields = $this->get_option('var_functions');
		if ($function_fields)
		{
			$defined_func = get_defined_functions();

			$output .= '{type: \'function\', value: ';

			$output .= $this->recursive_print(array('encode' => 'json', 'value' => $defined_func['user'], 'escape_key' => false, 'escape' => false, 'sep' => "}, \n{type: 'function', value: "));

			$output .= '}, ' . "\n";

			$rid++;
		}

		$include_fields = $this->get_option('var_includes');
		if ($include_fields)
		{
			$output .= '{type: \'include\', value: ';

			$output .= $this->recursive_print(array('encode' => 'json', 'value' => get_included_files(), 'escape_key' => false, 'escape' => false, 'sep' => "}, \n{type: 'include', value: "));

			$output .= '}, ' . "\n";

			$rid++;
		}

		$interface_fields = $this->get_option('var_interfaces');
		if ($interface_fields)
		{
			$output .= '{type: \'interface\', value: ';

			$output .= $this->recursive_print(array('encode' => 'json', 'value' => get_declared_interfaces(), 'escape_key' => false, 'escape' => false, 'sep' => "}, \n{type: 'interface', value: "));

			$output .= '}, ' . "\n";

			$rid++;
		}

		$classes_fields = $this->get_option('var_classes');
		if ($classes_fields)
		{
			$output .= '{type: \'classes\', value: ';

			$output .= $this->recursive_print(array('encode' => 'json', 'value' => get_declared_classes(), 'escape_key' => false, 'escape' => false, 'sep' => "}, \n{type: 'classes', value: "));

			$output .= '}, ' . "\n";

			$rid++;
		}

		if ($rid > 0)
			$output = substr(trim($output, "\n"), 0, -2);

		$output .= "\n" . ']);' . "\n";

		//Output update request
		$output .= 'lddevel.request(\'' . $page_id . '\');' . "\n";

		$output .= '}); </script>';

		if ($is_ajax)
			return $buffer . $output;

		if (stristr($buffer, '</body>') !== false)
			$buffer = preg_replace(',\</body\>,i', $output.'</body>', $buffer, 1);
		else
			$buffer = $buffer . $output;

		return $buffer;
	}

	/*
	 * Time a callback
	 */
	public function time($func, $args = null, $name = 'default_func_timer')
	{
		$name = trim($name);
		if (empty($name))
			$name = 'default_func_timer';

		// First measure the runtime of the func
		$start = microtime(true);

		if ($args)
			$result = call_user_func_array($func, (is_array($args) ? $args : array($args)));
		else
			$result = call_user_func($func);

		$end = microtime(true);

		// Check to see if a timer by that name exists
		if (isset($this->data['timers'][$name]))
		{
			$name = $name.uniqid();
		}

		// Save the timer
		$this->data['timers'][$name] = array();
		$this->data['timers'][$name]['start'] = $start;
		$this->data['timers'][$name]['end'] = $end;
		$this->data['timers'][$name]['time'] = $end - $start;
	}

	/*
	 *  Start, or add a tick to a timer.
	 */
	public function tick($name = 'default_timer', $callback = null)
	{
		$name = trim($name);
		if (empty($name))
			$name = 'default_timer';

		// Is this a brand new tick?
		if (isset($this->data['timers'][$name]))
		{
			$current_timer = $this->data['timers'][$name];
			$ticks = count($current_timer['ticks']);

			// Initialize the new time for the tick
			$time = microtime(true);
			$new_tick = array();
			$new_tick['time'] = $time - $current_timer['start'];

			// Use either the start time or the last tick for the diff
			if ($ticks > 0)
			{
				$last_tick = $current_timer['ticks'][$ticks- 1]['time'];
				$new_tick['diff'] = $new_tick['time'] - $last_tick;
			}
			else
			{
				$new_tick['diff'] = $new_tick['time'];
			}

			// Add the new tick to the stack of them
			$this->data['timers'][$name]['ticks'][] = $new_tick;
		}
		else
		{
			// Initialize a start time on the first tick
			$this->data['timers'][$name] = array();
			$this->data['timers'][$name]['start'] = microtime(true);
			$this->data['timers'][$name]['ticks'] = array();
		}

		// Run the callback for this tick if it's specified
		if (!is_null($callback) && is_callable($callback))
		{
			// After we've ticked, call the callback function
			call_user_func_array($callback, array(
				$this->data['timers'][$name]
			));
		}
	}

	/*
	 * Memory of a callback
	 */
	public function memory($func, $args = null, $name = 'default_func_memory')
	{
		$name = trim($name);
		if (empty($name))
			$name = 'default_func_memory';

		// First measure the runtime of the func
		$start = memory_get_usage(true);
		$start_peak = memory_get_peak_usage(true);

		if ($args)
			$result = call_user_func_array($func, (is_array($args) ? $args : array($args)));
		else
			$result = call_user_func($func);

		$end = memory_get_usage(true);
		$end_peak = memory_get_peak_usage(true);

		// Check to see if a timer by that name exists
		if (isset($this->data['memory'][$name]))
		{
			$name = $name.uniqid();
		}

		// Save the timer
		$this->data['memory'][$name] = array();
		$this->data['memory'][$name]['start'] = $start;
		$this->data['memory'][$name]['end'] = $end;
		$this->data['memory'][$name]['start_peak'] = $start_peak;
		$this->data['memory'][$name]['end_peak'] = $end_peak;
		$this->data['memory'][$name]['memory'] = $end - $start;
		$this->data['memory'][$name]['memory_peak'] = $end_peak - $start_peak;
	}

	/*
	 *  Start, or add a tick to a memory timer.
	 */
	public function memory_tick($name = 'default_memory', $callback = null)
	{
		$name = trim($name);
		if (empty($name))
			$name = 'default_memory';

		// Is this a brand new tick?
		if (isset($this->data['memory'][$name]))
		{
			$current_timer = $this->data['memory'][$name];
			$ticks = count($current_timer['ticks']);

			// Initialize the new time for the tick
			$end = memory_get_usage(true);
			$end_peak = memory_get_peak_usage(true);

			$new_tick = array();
			$new_tick['end'] = $end;
			$new_tick['end_peak'] = $end_peak;
			$new_tick['memory'] = $end - $current_timer['start'];
			$new_tick['memory_peak'] = $end_peak - $current_timer['start_peak'];

			// Use either the start time or the last tick for the diff
			if ($ticks > 0)
			{
				$last_tick = $current_timer['ticks'][$ticks- 1];
				$new_tick['diff'] = $new_tick['memory'] - $last_tick['memory'];
				$new_tick['diff_peak'] = $new_tick['memory_peak'] - $last_tick['memory_peak'];
			}
			else
			{
				$new_tick['diff'] = $new_tick['memory'];
				$new_tick['diff_peak'] = $new_tick['memory_peak'];
			}

			// Add the new tick to the stack of them
			$this->data['memory'][$name]['ticks'][] = $new_tick;
		}
		else
		{
			// Initialize a start time on the first tick
			$this->data['memory'][$name] = array();
			$this->data['memory'][$name]['start'] = memory_get_usage(true);
			$this->data['memory'][$name]['start_peak'] = memory_get_peak_usage(true);
			$this->data['memory'][$name]['ticks'] = array();
		}

		// Run the callback for this tick if it's specified
		if (!is_null($callback) && is_callable($callback))
		{
			// After we've ticked, call the callback function
			call_user_func_array($callback, array(
				$this->data['memory'][$name]
			));
		}
	}

	/*
	 * Output logs
	 */
	public function log($message, $params = array())
	{
		$default = array(
			'type' => 'INFO',
			'output' => false,
		);
		$params = array_merge($default, $params);

		$new_params = array();
		foreach($params as $param=>$value)
		{
			if (!array_key_exists($param, $default))
				$new_params[$param] = $value;
		}

		$new_message = $this->dump($message, $new_params);

		$this->data['logs'][] = array($new_message, $params['type']);

		if ($params['output'])
		{
			traceLog($new_message, $params['type']);
		}
	}

	/*
	 * Recursively output variables
	 */
	public function recursive_print($params = array())
	{
		$default = array(
			'_depth' => 0,
			'varname' => '',
			'value' => '',
			'sep' => "<br />\n",
			'key' => true,
			'escape_key' => true,
			'escape' => true,
			'encode' => ''
		);
		$params = array_merge($default, $params);

		$output = array();

		if( is_array($params['value']) )
		{
			if (strlen($params['varname']))
			{
				$save = $params['varname'] . " = array()";

				if ($params['encode'] == 'json')
					$save = $this->safe_parameter($save);

				$output[] = $save;
			}

			foreach ($params['value'] as $key => $val)
			{
				if ($params['key'])
				{
					if ($params['varname'])
						$name = $params['escape_key'] ? $params['varname'] . "['" . $key . "']" : $params['varname'] . "[" . $key . "]";
					else
						$name = $params['escape_key'] ? "'" . $key . "'" : $key;
				}
				else
				{
					$name = '';
				}

				$save = $this->recursive_print(array_merge($params, array('varname' => $name, 'value' => $val, '_depth' => $params['_depth'] + 1)));
			
				$output[] = $save;
			}
		}
		else if( is_object($params['value']) )
		{
			if (strlen($params['varname']))
			{
				$save = $params['varname'] . " = new stdClass()";

				if ($params['encode'] == 'json')
					$save = $this->safe_parameter($save);

				$output[] = $save;
			}

			$obj = get_object_vars($params['value']);
			foreach ($obj as $key => $val)
			{
				if ($params['key'])
				{
					if ($params['varname'])
						$name = $params['varname'] . "->" . $key;
					else
						$name = $key;
				}
				else
				{
					$name = '';
				}

				$save = $this->recursive_print(array_merge($params, array('varname' => $name, 'value' => $val, '_depth' => $params['_depth'] + 1)));
			
				$output[] = $save;
			}
		}
		else
		{
			$val = $params['value'];

			if (is_null($val))
				$val = 'null';
			else if (is_string($val))
				$val = $params['escape'] ? "'" . $val . "'" : $val;

			if ($params['key'] && strlen($params['varname']))
				$front = $params['varname'] . ' = ';
			else
				$front = '';

			$val = str_replace('\\', '\\\\', $val);

			$save = $front . $val;

			if ($params['encode'] == 'json')
				$save = $this->safe_parameter($save);

			$output[] = $save;
		}

		return implode($params['sep'], $output);
	}
	
	/*
	 * Dump out variables
	 * This function was adapted from TVarDumper class
	 *
	 * @author Qiang Xue <qiang.xue@gmail.com>
	 * @link http://www.pradosoft.com/
	 * @copyright Copyright &copy; 2005-2008 PradoSoft
	 * @license http://www.pradosoft.com/license/
	 */
	public function dump($var, $params = array())
	{
		$default = array(
			'_current_depth' => 0,
			'depth' => 10,
			'spaces' => 2,
			'highlight' => false,
			'exclude_ar' => true
		);
		$params = array_merge($default, $params);

		$output = '';

		switch(strtolower(gettype($var)))
		{
			case 'unknown type':
				$output = '(unknown)';
			break;

			case 'resource':
				$output = '(resource)';
			break;

			case 'null':
				$output = 'null';
			break;

			case 'boolean':
				$output = $var ? 'true' : 'false';
			break;

			case 'integer':
			case 'double':
				$output = $var;
			break;

			case 'string':
				$output = "'" . $var . "'";
			break;

			case 'array':
				if ($params['depth'] <= $params['_current_depth'])
				{
					$output = 'array(...)';
				}
				else if (empty($var))
				{
					$output = 'array()';
				}
				else
				{
					$keys = array_keys($var);
					$spaces = str_repeat(' ', $params['_current_depth'] * $params['spaces']);
					$output = 'array' . "\n" . $spaces . '(';

					foreach ($keys as $key)
					{
						$output .= "\n" . $spaces . str_repeat(' ', $params['spaces']) . '[' . $key . '] => ';
						$output .= $this->dump($var[$key], array_merge($params, array('_current_depth' => $params['_current_depth'] + 1)));
					}

					$output .= "\n" . $spaces . ')';
				}
			break;

			case 'object':
				$signature = md5(serialize($var));
				$object_id = array_search($signature, $this->_dump_objects, true);
				if ($object_id !== false)
				{
					$output = 'object(' . get_class($var) . ')#' . ($object_id + 1) . ' (...)';
				}
				else if ($params['depth'] <= $params['_current_depth'])
				{
					$output = 'object(' . get_class($var) . ') (...)';
				}
				else
				{
					array_push($this->_dump_objects, $signature);

					$class = get_class($var);
					$members = (array)$var;
					$keys = array_keys($members);
					$spaces = str_repeat(' ', $params['_current_depth'] * $params['spaces']);
					$output = 'object(' . $class . ')' . ($object_id ? '#' . $object_id : '') . "\n" . $spaces . '(';

					if ($params['exclude_ar'])
					{
						$ar = is_subclass_of($var, 'Db_ActiveRecord');

						$arkeys = array(
							'table_name', 'implement', 'auto_footprints_visible', 'belongs_to', 'has_one', 'has_and_belongs_to_many', 'has_many',
							'calculated_columns', 'custom_columns', 'objectId', '_columns_def', 'auto_create_timestamps', 'auto_update_timestamps',
							'auto_timestamps', 'has_models', 'validation', 'form_elements', 'formTabIds', 'formTabVisibility', 'formTabCssClasses',
							'model_options', 'native_controller', 'use_straight_join', 'parts', 'encrypted_columns', 'Db_ActiveRecord:__locked',
							'Db_WhereBase:where', 'encrypted_columns', 'serialize_associations', 'primary_key', 'default_sort', 'strict',
							'auto_footprints_default_invisible',

							//hide the fetched columns since they are duplicates, we are not using the fetched columns because the classes 
							//can set variables and defaults outside of the db
							'fetched'
						);

						$arclass = array(
							'Phpr_ValidationRules'
						);

						if (in_array($class, $arclass))
						{
							$output .= 'skipped)';
							return $output;
						}
					}

					foreach ($keys as $key)
					{
						$display = strtr(trim($key), array("\0" => ':'));

						if ($params['exclude_ar'])
						{
							if ($ar && in_array($display, $arkeys))
								continue;

							if (substr($display, 0, 2) == '*:')
								continue;
						}

						$output .= "\n" . $spaces . str_repeat(' ', $params['spaces']) . '[' . $display . '] => ';
						$output .= $this->dump($members[$key], array_merge($params, array('_current_depth' => $params['_current_depth'] + 1)));
					}

					$output .= "\n" . $spaces . ')';
				}
			break;
		}

		if ($params['highlight'])
		{
			$highlight = highlight_string("<?php\n" . $output, true);
			return preg_replace('/&lt;\\?php<br \\/>/', '', $highlight, 1);
		}

		return $output;
	}

	/*
	 * Stop execution of the page and force output buffer
	 */
	public function close()
	{
		ob_end_flush();
		exit;
	}

	/*
	 *  Format sql string
	 */
	public function sql_format($sql, $params = array())
	{
		$default = array(
			'highlight' => false,
		);
		$params = array_merge($default, $params);

		if ($params['highlight'])
		{
			return LDDevel_SqlFormatter::highlight($sql);
		}

		return LDDevel_SqlFormatter::format($sql, false);
	}

	/*
	 * Backtrace functionality
	 */
 	public function backtrace()
    {
        $backtrace = debug_backtrace();

        $backtrace = array_slice($backtrace, 4);
        if (empty($backtrace)) {
            return '';
        }

        // Iterate backtrace
        $calls = array();
        foreach ($backtrace as $i => $call) {
            if (!isset($call['file'])) {
                $call['file'] = '(null)';
            }
            if (!isset($call['line'])) {
                $call['line'] = '0';
            }
            $location = $call['file'] . ':' . $call['line'];
            $function = (isset($call['class'])) ?
                $call['class'] . (isset($call['type']) ? $call['type'] : '.') . $call['function'] :
                $call['function'];

            $params = '';
            if (isset($call['args'])) {
                $args = array();
                foreach ($call['args'] as $arg) {
                    if (is_array($arg) || is_object($arg)) {
                        $desc = print_r($arg, true);
                        $desc = preg_replace('/\s+/', ' ', $desc);
                        $args[] = $desc;
                    } else {
                        $args[] = $arg;
                    }
                }
                $params = implode(', ', $args);
            }

            $calls[] = sprintf('#%d  %s(%s) called at [%s]',
                $i,
                $function,
                $params,
                $location);
        }

        $message = implode("<br />", $calls);
        return $message;
    }

	/*
	 * Convert filesize to text
	 */
	public function get_file_size($size)
	{
		if ($size <= 0)
			return $size;

		$units = array('b', 'kb', 'mb', 'gb', 'tb', 'pb', 'eb');
		return @round($size / pow(1024, ($i = floor(log($size, 1024)))), 2).' '.strtoupper($units[$i]);
	}

	/*
	 * Prepare variables for json
	 */
	public function safe_parameter($str)
	{
		$result = $str;
		$result = preg_replace( '/\s+/', ' ', $result );
		$result = json_encode($result);
		return $result;
	}

	/*
	 * Encode for json
	 */
	public function encode_parameter($str)
	{
		$result = $str;
		$result = json_encode($result);
		return $result;
	}

	/*
	 * Clean sql file
	 */
	public function clean_var($str)
	{
		$str = preg_replace( '/\s+/', ' ', $str );
		return $str;
	}

    /*
     * Loads options from database
     */
    private function load_options()
    {
        $options = array();

        $test = LDDevel_ModuleSettings::create('lddevel', 'settings');
        foreach($test->column_definitions as $t)
        {
            $options[$t->dbName] = $test->{$t->dbName};

        }

        $this->options = $options;
    }

    /*
     * Get option
     */
    private function get_option($key, $default = null)
    {
        if (isset($this->options[$key]))
            return $this->options[$key];

        return $default;
    }

    /*
     * Save configuration options
     */
    public function save_config($options)
    {
		$session_key = uniqid(get_class($this), true);

		$obj = LDDevel_ModuleSettings::create('lddevel', 'settings');
		foreach($obj->custom_columns as $code=>$type)
		{
			if ($type != db_bool)
				continue;

			if (!isset($options[$code]))
				$options[$code] = 0;
		}

		$obj->save($options, $session_key);
		
		return true;
    }

    /*
     * Checkbox option
     */
    private function checkbox_option($key)
    {
    	return isset($this->options[$key]) && $this->options[$key] ? 'checked="checked"' : '';
    }

	/*
	 * Detect if we are in the backend
	 */
	public function is_backend()
	{
		$backend_url = '/' . Core_String::normalizeUri(Phpr::$config->get('BACKEND_URL', 'backend'));
		$current_url = '/' . Core_String::normalizeUri(isset(Phpr::$request->get_fields['q']) ? Phpr::$request->get_fields['q'] : '');
		return stristr($current_url, $backend_url) !== false;
	}
}
