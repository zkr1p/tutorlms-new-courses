<?php declare(strict_types=1);

namespace boctulus\TutorNewCourses\core\libs;

/*
    Cron class

    -- tomada de LearnDash --

    Requiere de un hook como:

        add_action('my_cron_hook', array( __CLASS__, 'my_cron_cb' ));

    Entonces

        public static function my_cron_cb() {
            // ...
        }

    Esta tarea programada se crea y se programa para ejecutarse "cada minuto" (según la frecuencia definida en 'per_minute') 
    utilizando las funciones de WordPress wp_schedule_event.

    Método deregister_hook:

    Este método se utiliza para eliminar cualquier tarea programada previamente registrada con el nombre 'my_cron_hook' cuando el plugin se desactive.

    Nota: 

    Debe instanciarse para que funcione

*/
class Crons {
	
	/**
	 * Hook functions
	 */
	public function __construct() {
		$current_plugin_index_file = ROOT_PATH . 'index.php';

		add_filter( 'cron_schedules', array( $this, 'add_cron_schedule' ) );
		add_action( 'admin_init', array( $this, 'register_cron' ) );
		register_deactivation_hook($current_plugin_index_file, array( $this, 'deregister_hook' ) );
	}

	/**
	 * Add cron schedule
	 * 
	 * @param array $schedules Cron schedules
	 */
	public function add_cron_schedule( $schedules ) {
		$schedules['per_minute'] = array(
			'interval' => MINUTE_IN_SECONDS,
			'display'  => 'Una vez por minuto', // podria usar __() para traducirlo
		);

		return $schedules;
	}

	/**
	 * Register cron hook
	 */
	public function register_cron() {
		if ( ! wp_next_scheduled( 'my_cron_hook' ) ) {
			wp_schedule_event( time(), 'per_minute', 'my_cron_hook' );
		}
	}

	public function deregister_hook() {
		wp_clear_scheduled_hook( 'my_cron_hook' );
	}
}

