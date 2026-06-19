<?php

return [
    /*
     | How many days of database backups to keep. Read from config (not env() directly in the
     | command) so it still works under `config:cache`, where env() returns null outside config.
     */
    'keep_days' => (int) env('BACKUP_KEEP_DAYS', 30),
];
