<?php
$faq_dir = __DIR__ . "/faq";
$filename = isset($_GET['file']) ? basename($_GET['file']) : '';

// Liefert Liste aller JSON-Dateien im /faq/ Ordner
if ($filename === "list") {
  $files = array_values(array_filter(scandir($faq_dir), function($f) {
    return pathinfo($f, PATHINFO_EXTENSION) === "json";
  }));
  header("Content-Type: application/json");
  echo json_encode($files);
  exit;
}

// Normales Laden einer bestimmten Datei
$path = $faq_dir . "/" . $filename;

if (file_exists($path)) {
  header("Content-Type: application/json");
  readfile($path);
} else {
  http_response_code(404);
  echo json_encode(["error" => "Not found"]);
}
?>
