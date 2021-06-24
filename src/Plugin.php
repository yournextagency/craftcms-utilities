<?php

namespace yna\utilities;

use Craft;
use craft\db\Connection;
use craft\events\BackupEvent;
use craft\helpers\FileHelper;
use DateTime;
use DateTimeZone;
use yii\base\Event;
use function sprintf;

class Plugin extends \craft\base\Plugin
{
    public function init()
    {
        parent::init();

        $this->initBackupsPruning((int) getenv('BACKUP_RETENTION') ?: 14);
    }

    /**
     * Deletes zipped backups older then $retentionDays
     *
     * @param int $retentionDays
     */
    private function initBackupsPruning(int $retentionDays = 14): void
    {
        if (Craft::$app->getRequest()->isConsoleRequest === false) {
            return;
        }

        Event::on(
            Connection::class,
            Connection::EVENT_AFTER_CREATE_BACKUP,
            static function (BackupEvent $event) use ($retentionDays) {
                $timeZone = new DateTimeZone('UTC');
                $dividerDate = (new DateTime(null, $timeZone))->modify(sprintf('-%d days', $retentionDays));

                // Backup files
                $files = FileHelper::findFiles(Craft::$app->getPath()->getDbBackupPath(), ['only' => ['*.zip']]);

                foreach ($files as $file) {
                    // Last modified date of file
                    $modified = (new DateTime('@'.FileHelper::lastModifiedTime($file), $timeZone));

                    if ($modified < $dividerDate) {
                        FileHelper::unlink($file);
                    }
                }
            }
        );
    }
}
