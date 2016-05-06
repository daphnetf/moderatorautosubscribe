<?php
/**
*
* @package phpBB Extension - Moderator autosubscribe
* @copyright (c) 2016, University of Freiburg, Chair of Algorithms and Data Structures.
* @license https://opensource.org/licenses/BSD-2-Clause BSD 2-Clause License
*
*/

namespace daphnetf\moderatorautosubscribe\migrations;

class cron_migration extends \phpbb\db\migration\migration
{
   public function effectively_installed()
   {
      return isset($this->config['moderator_autosubscribe_gc']);
   }

   static public function depends_on()
   {
      return array('\phpbb\db\migration\data\v310\dev');
   }

   public function update_data()
   {
      return array(
         array('config.add', array('moderator_autosubscribe_last_gc', 0)), // last run
         array('config.add', array('moderator_autosubscribe_gc', (60 * 10))), // seconds between run; 10 minutes
      );
   }
}