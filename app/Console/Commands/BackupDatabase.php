<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Carbon\Carbon;

class BackupDatabase extends Command
{
    // Nazwa, którą będziemy wywoływać w terminalu
    protected $signature = 'db:backup';
    protected $description = 'Tworzy zrzut bazy danych i usuwa stare kopie';

    public function handle()
    {
        // Pobieramy dane dostępowe z pliku .env
        $database = env('DB_DATABASE');
        $username = env('DB_USERNAME');
        $password = env('DB_PASSWORD');
        $host = env('DB_HOST', '127.0.0.1');

        $dumpCommand = env('DB_DUMP_PATH', 'mysqldump');

        $date = Carbon::now()->format('Y-m-d_H-i-s');
        $filename = "backup_{$date}.sql";
        $storagePath = storage_path('app/backups');

        // Tworzymy folder /storage/app/backups, jeśli nie istnieje
        if (!File::exists($storagePath)) {
            File::makeDirectory($storagePath, 0755, true);
        }

        $filePath = "{$storagePath}/{$filename}";

        // Zlecenie dla systemu operacyjnego (wymaga zainstalowanego mysqldump na serwerze)
        $command = sprintf(
            '"%s" --host="%s" --user="%s" --password="%s" "%s" > "%s"',
            $dumpCommand, $host, $username, $password, $database, $filePath
        );

        exec($command, $output, $returnVar);

        if ($returnVar === 0) {
            $this->info("Pomyślnie utworzono kopię zapasową: {$filename}");
        } else {
            $this->error("Wystąpił błąd podczas tworzenia kopii zapasowej. Sprawdź, czy mysqldump jest zainstalowane.");
            return;
        }

        // --- CZYSZCZENIE STARYCH KOPII (starszych niż 30 dni) ---
        $files = File::files($storagePath);
        foreach ($files as $file) {
            if (now()->diffInDays(Carbon::createFromTimestamp($file->getMTime())) > 30) {
                File::delete($file);
                $this->info("Usunięto przestarzałą kopię: " . $file->getFilename());
            }
        }
    }
}