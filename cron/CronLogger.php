<?php
    class CronLogger
    {
        private string $logFile;

        public function __construct(string $logFile = '')
        {
            $this->logFile = $logFile ?: __DIR__ . '/../logs/cron.log';    

            //Log dosyasinin bulunacagi klasor yolunu al
            $logDir = dirname($this->logFile);

            //Klasor yoksa ic ice klasorleri destekleyecek sekilde olustur
            if (!is_dir($logDir)) {
                mkdir($logDir, 0777, true);
            }
        }

        public function log(string $message): void
        {
            $line = "[" . date('H:i:s') . "] " . $message . PHP_EOL;
            echo $line;
            file_put_contents($this->logFile, $line, FILE_APPEND | LOCK_EX); 
        }
    }
    
?>