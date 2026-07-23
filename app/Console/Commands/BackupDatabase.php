<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

class BackupDatabase extends Command
{
    protected $signature = 'db:backup';
    protected $description = 'Tworzy zrzut bazy danych, usuwa stare kopie i wysyła plik na e-mail';

    public function handle()
    {
        $database = env('DB_DATABASE');
        $username = env('DB_USERNAME');
        $password = env('DB_PASSWORD');
        $host = env('DB_HOST', '127.0.0.1');
        
        $dumpCommand = env('DB_DUMP_PATH', 'mysqldump');

        $date = Carbon::now()->format('Y-m-d_H-i-s');
        $filename = "backup_{$date}.sql";
        $storagePath = storage_path('app/backups');

        if (!File::exists($storagePath)) {
            File::makeDirectory($storagePath, 0755, true);
        }

        $filePath = "{$storagePath}/{$filename}";

        $command = sprintf(
            '"%s" --host="%s" --user="%s" --password="%s" "%s" > "%s"',
            $dumpCommand, $host, $username, $password, $database, $filePath
        );

        exec($command, $output, $returnVar);

        if ($returnVar === 0) {
            $this->info("Pomyślnie utworzono kopię zapasową: {$filename}");

            // --- WYSYŁKA E-MAIL ---
            $recipient = env('BACKUP_EMAIL_TO');

            if ($recipient) {
                try {
                    // Tworzymy prostą wiadomość tekstową i załączamy plik
                    Mail::raw("W załączniku znajduje się automatyczny zrzut bazy danych ({$database}) z dnia {$date}.", function ($message) use ($recipient, $filePath, $filename) {
                        $message->to($recipient)
                                ->subject("Kopia zapasowa bazy danych - Kolegium")
                                ->attach($filePath, [
                                    'as' => $filename,
                                    'mime' => 'text/plain',
                                ]);
                    });
                    
                    $this->info("Kopia zapasowa została wysłana na adres: {$recipient}");
                } catch (\Exception $e) {
                    $this->error("Nie udało się wysłać e-maila: " . $e->getMessage());
                }
            } else {
                $this->warn("Zmienna BACKUP_EMAIL_TO nie jest ustawiona w pliku .env. Zrzut wykonany, ale pominięto wysyłkę.");
            }

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