<?php
/*
Plugin Name: StatsFC Prediction League
Plugin URI: https://statsfc.com/docs/wordpress
Description: StatsFC Prediction League
Version: 1.0
Author: Will Woodward
Author URI: http://willjw.co.uk
License: GPL2
*/

/*  Copyright 2013  Will Woodward  (email : will@willjw.co.uk)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

define('STATSFC_PREDICTIONLEAGUE_ID',   'StatsFC_PredictionLeague');
define('STATSFC_PREDICTIONLEAGUE_NAME', 'StatsFC Prediction League');

/**
 * Adds StatsFC widget.
 */
class StatsFC_PredictionLeague extends WP_Widget {
	public $isShortcode = false;

	private static $defaults = array(
		'title'       => '',
		'key'         => '',
		'competition' => ''
	);

	/**
	 * Register widget with WordPress.
	 */
	public function __construct() {
		parent::__construct(STATSFC_PREDICTIONLEAGUE_ID, STATSFC_PREDICTIONLEAGUE_NAME, array('description' => 'StatsFC Prediction League'));
	}

	/**
	 * Back-end widget form.
	 *
	 * @see WP_Widget::form()
	 *
	 * @param array $instance Previously saved values from database.
	 */
	public function form($instance) {
		$instance    = wp_parse_args((array) $instance, self::$defaults);
		$title       = strip_tags($instance['title']);
		$key         = strip_tags($instance['key']);
		$competition = strip_tags($instance['competition']);
		?>
		<p>
			<label>
				<?php _e('Title', STATSFC_PREDICTIONLEAGUE_ID); ?>:
				<input class="widefat" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo esc_attr($title); ?>">
			</label>
		</p>
		<p>
			<label>
				<?php _e('StatsFC Key', STATSFC_PREDICTIONLEAGUE_ID); ?>:
				<input class="widefat" name="<?php echo $this->get_field_name('key'); ?>" type="text" value="<?php echo esc_attr($key); ?>">
			</label>
		</p>
		<p>
			<label>
				<?php _e('Competition', STATSFC_PREDICTIONLEAGUE_ID); ?>:
				<?php
				try {
					$data = $this->_fetchData('https://api.statsfc.com/crowdscores/competitions.php');

					if (empty($data)) {
						throw new Exception;
					}

					$json = json_decode($data);

					if (isset($json->error)) {
						throw new Exception;
					}
					?>
					<select class="widefat" name="<?php echo $this->get_field_name('competition'); ?>">
						<option></option>
						<?php
						foreach ($json as $comp) {
							echo '<option value="' . esc_attr($comp->key) . '"' . ($comp->key == $competition ? ' selected' : '') . '>' . esc_attr($comp->name) . '</option>' . PHP_EOL;
						}
						?>
					</select>
				<?php
				} catch (Exception $e) {
				?>
					<input class="widefat" name="<?php echo $this->get_field_name('competition'); ?>" type="text" value="<?php echo esc_attr($competition); ?>">
				<?php
				}
				?>
			</label>
		</p>
	<?php
	}

	/**
	 * Sanitize widget form values as they are saved.
	 *
	 * @see WP_Widget::update()
	 *
	 * @param array $new_instance Values just sent to be saved.
	 * @param array $old_instance Previously saved values from database.
	 *
	 * @return array Updated safe values to be saved.
	 */
	public function update($new_instance, $old_instance) {
		$instance                = $old_instance;
		$instance['title']       = strip_tags($new_instance['title']);
		$instance['key']         = strip_tags($new_instance['key']);
		$instance['competition'] = strip_tags($new_instance['competition']);

		return $instance;
	}

	/**
	 * Front-end display of widget.
	 *
	 * @see WP_Widget::widget()
	 *
	 * @param array $args     Widget arguments.
	 * @param array $instance Saved values from database.
	 */
	public function widget($args, $instance) {
		extract($args);

		$title       = apply_filters('widget_title', $instance['title']);
		$key         = $instance['key'];
		$competition = $instance['competition'];

		$html  = $before_widget;
		$html .= $before_title . $title . $after_title;

		try {
			if (strlen($competition) == 0) {
				throw new Exception('Please choose a competition from the widget options');
			}

			wp_register_script(STATSFC_PREDICTIONLEAGUE_ID . '-js', plugins_url('script.js', __FILE__));
			wp_enqueue_script(STATSFC_PREDICTIONLEAGUE_ID . '-js');

			$key         = esc_attr($key);
			$competition = esc_attr($competition);

			$html .= <<< HTML
			<iframe id="statsfc-prediction-league" src="https://pl.statsfc.com/{$key}/{$competition}" width="100%" height="600" scrolling="no" frameborder="no"></iframe>
HTML;
		} catch (Exception $e) {
			$html .= '<p style="text-align: center;">StatsFC.com – ' . esc_attr($e->getMessage()) . '</p>' . PHP_EOL;
		}

		$html .= $after_widget;

		if ($this->isShortcode) {
			return $html;
		} else {
			echo $html;
		}
	}

	private function _fetchData($url) {
		if (function_exists('curl_exec')) {
			return $this->_curlRequest($url);
		} else {
			return $this->_fopenRequest($url);
		}
	}

	private function _curlRequest($url) {
		$ch = curl_init();

		curl_setopt_array($ch, array(
			CURLOPT_AUTOREFERER		=> true,
			CURLOPT_HEADER			=> false,
			CURLOPT_RETURNTRANSFER	=> true,
			CURLOPT_TIMEOUT			=> 5,
			CURLOPT_URL				=> $url
		));

		$data = curl_exec($ch);
		if (empty($data)) {
			$data = $this->_fopenRequest($url);
		}

		curl_close($ch);

		return $data;
	}

	private function _fopenRequest($url) {
		return file_get_contents($url);
	}

	public static function shortcode($atts) {
		$args = shortcode_atts(static::$defaults, $atts);

		$widget              = new static;
		$widget->isShortcode = true;

		return $widget->widget(array(), $args);
	}
}

// register StatsFC widget
add_action('widgets_init', create_function('', 'register_widget("' . STATSFC_PREDICTIONLEAGUE_ID . '");'));
add_shortcode('statsfc-prediction-league', STATSFC_PREDICTIONLEAGUE_ID . '::shortcode');
