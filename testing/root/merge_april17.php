<?php {
  // Create temporary directory for this session
  \ = 'C:\\xampp\\mysql\\data_temp_april17';
  if (!is_dir(\)) mkdir(\, 0777, true);
  
  // Log file
  \ = \ . '\\merge.log';
  \ = function(\) use (\) {
    file_put_contents(\, date('Y-m-d H:i:s') . ' - ' . \ . PHP_EOL, FILE_APPEND);
    echo \ . PHP_EOL;
  };
  
  \('Starting April 17 table extraction...');
  
  // April 17 backup files 
  \ = 'C:\\\\xampp\\\\mysql\\\\data_user_backup_20260417_190141\\\\corelynk_db';
  
  if (!is_dir(\)) {
    \('ERROR: April 17 backup directory not found!');
    exit(1);
  }
  
  // List all .frm files (table structure files)
  \ = glob(\ . '\\\\*.frm');
  \('Found ' . count(\) . ' table files in April 17 backup');
  
  // Connect to current MySQL (April 16 with working privilege tables)
  try {
    \ = new mysqli('localhost', 'root', '', 'corelynk_db');
    if (\->connect_error) {
      \('MySQL connection failed: ' . \->connect_error);
      exit(1);
    }
    \('? Connected to MySQL');
  } catch (Exception \) {
    \('ERROR: ' . \->getMessage());
    exit(1);
  }
  
  // For each April 17 table, check if it exists and has updates
  foreach (\ as \) {
    \ = basename(\, '.frm');
    \ = str_replace('.frm', '.ibd', \);
    
    if (!file_exists(\)) continue;
    
    \ = filemtime(\);
    \ = strtotime('2026-04-16 21:29:00');
    
    // Check if file was modified after April 16 backup
    if (\ > \) {
      \(\"Table '\' was modified in April 17\");
    }
  }
  
  \('? Analysis complete');
?>
