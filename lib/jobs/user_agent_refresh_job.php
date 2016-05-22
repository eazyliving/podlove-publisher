<?php 
namespace Podlove\Jobs;

use \Podlove\Model\UserAgent;
use \Podlove\Model\DownloadIntentClean;

class UserAgentRefreshJob {
	use JobTrait;

	public function __construct($args = []) {
		// maybe separate func so that argument parsing can be done in trait?
		$defaults = [
			'agents_total' => UserAgent::count(),
			'agents_per_step' => 500
		];

		$this->args = wp_parse_args($args, $defaults);
		$this->hooks['finished'] = [__CLASS__, 'delete_bots_from_clean_downloadintents'];

		$this->state = ['previous_id' => 0];
	}

	public function get_total_steps() {
		return $this->args['agents_total'];
	}

	protected function do_step() {

		$previous_id     = (int) $this->state['previous_id'];
		$agents_per_step = (int) $this->args['agents_per_step'];

		$agents = UserAgent::find_all_by_where(sprintf("id > %d ORDER BY id ASC LIMIT %d", $previous_id, $agents_per_step));

		$progress = 0;
		foreach ($agents as $ua) {
	        $ua->parse()->save();
	        $progress++;
	    }

		$this->state['previous_id'] = $ua->id;

		return $progress;
	}

	public static function delete_bots_from_clean_downloadintents() {
		global $wpdb;

		$sql = "DELETE FROM `" . DownloadIntentClean::table_name() . "` WHERE `user_agent_id` IN (
			SELECT id FROM `" . UserAgent::table_name() . "` ua WHERE ua.bot
		)";

		$wpdb->query($sql);
	}
}