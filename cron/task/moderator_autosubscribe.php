<?php
/**
*
* @package phpBB Extension - Moderator autosubscribe
* @copyright (c) 2016, University of Freiburg, Chair of Algorithms and Data Structures.
* @license https://opensource.org/licenses/BSD-2-Clause BSD 2-Clause License
*
*/

namespace daphnetf\moderatorautosubscribe\cron\task;

/**
* Queue cron task. Sends email and jabber messages queued by other scripts.
*/
class moderator_autosubscribe extends \phpbb\cron\task\base
{
	protected $config;
	protected $db;
	protected $notification_manager;
	protected $php_ext;
	protected $phpbb_root_path;

	/**
	* Constructor.
	*
	* @param string $phpbb_root_path The root path
	* @param string $php_ext The PHP file extension
	* @param \phpbb\config\config $config The config
	* @param \phpbb\db\driver\driver_interface $db The db connection
	* @param \phpbb\notification\manager $notification_manager Notification manager
	*/
	public function __construct($phpbb_root_path, $php_ext, \phpbb\config\config $config, \phpbb\db\driver\driver_interface $db, \phpbb\notification\manager $notification_manager)
	{
		$this->phpbb_root_path = $phpbb_root_path;
		$this->php_ext = $php_ext;
		$this->config = $config;
		$this->db = $db;
		$this->notification_manager = $notification_manager;
	}

	protected function subscribe_user($user_id, $forum_id)
	{
		$this->notification_manager->add_subscription('notification.type.topic', 0, 'notification.method.email', $user_id);
		$this->notification_manager->add_subscription('notification.type.post', 0, 'notification.method.email', $user_id);
		$this->notification_manager->add_subscription('notification.type.topic', $forum_id, 'notification.method.email', $user_id);
		$this->notification_manager->add_subscription('notification.type.post', $forum_id, 'notification.method.email', $user_id);

		$sql = "UPDATE " . FORUMS_WATCH_TABLE . "
			SET notify_status = " . NOTIFY_YES . "
			WHERE forum_id = " . $forum_id . "
				AND user_id = " . $user_id;
		$this->db->sql_query($sql);

		if (!$this->db->sql_affectedrows()) {
			print($user_id . '::' . $forum_id);
			print("->INSERT");
			$sql = "INSERT INTO " . FORUMS_WATCH_TABLE . "
				(forum_id, user_id, notify_status)
				VALUES (" . $forum_id . ", " . $user_id . ", " . NOTIFY_YES . ")";
			$this->db->sql_query($sql);
			print("\n");
		}
	}

	/**
	* Runs this cron task.
	*
	* @return null
	*/
	public function run()
	{
		$sql_array = array(
			'SELECT'	=> 'm.*',

			'FROM'		=> array(
				MODERATOR_CACHE_TABLE	=> 'm',
			),
		);

		// We query every forum here because for caching we should not have any parameter.
		$sql = $this->db->sql_build_query('SELECT', $sql_array);
		$result = $this->db->sql_query($sql, 600);

		while ($row = $this->db->sql_fetchrow($result))
		{
			$user_id = $row['user_id'];
			$group_id = $row['group_id'];
			$forum_id = $row['forum_id'];

			if (is_numeric($group_id) && intval($group_id) > 0)
			{
				$sql_array = array(
					'SELECT'	=> 'g.*',
					
					'FROM'		=> array(
						USER_GROUP_TABLE	=> 'g',
					),
					
					'WHERE'		=> 'g.group_id = '.$group_id,
				);
				$sql2 = $this->db->sql_build_query('SELECT', $sql_array);
				$result2 = $this->db->sql_query($sql2);
				while ($row2 = $this->db->sql_fetchrow($result2))
				{
					$this->subscribe_user($row2['user_id'], $row['forum_id']);
				}
				$this->db->sql_freeresult($result2);
			}
			else
			{
				$this->subscribe_user($row['user_id'], $row['forum_id']);
			}
		}
		$this->db->sql_freeresult($result);
		$this->config->set('moderator_autosubscribe_last_gc', time());
	}

	/**
	* Returns whether this cron task can run, given current board configuration.
	*
	* @return bool
	*/
	public function is_runnable()
	{
		return true;
	}

	/**
	* Returns whether this cron task should run now, because enough time
	* has passed since it was last run.
	*
	* @return bool
	*/
	public function should_run()
	{
		return $this->config['moderator_autosubscribe_last_gc'] < time() - $this->config['moderator_autosubscribe_gc'];
	}
}
