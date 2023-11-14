<?php
$this->functionHandlers['php_code'] = function ($args) {
    if ($this->ash->debug) echo ("debug: php_code(" . print_r($args, true) . ")\n");
    $php_code = $args['php_code'] ?? "";
    $stdOut = "";
    $sdtErr = "";
    $exitCode = 0;
    if (empty($php_code)) {
        $error = "Error (ash): Missing required fields.";
        if ($this->ash->debug) echo ("debug: php_code() error: $error\n");
        return ["stdout" => "", "stderr" => $error, "exit_code" => -1];
    }
    $random_file = "C:\\Users\\\$USERPROFILE\\AppData\\Local\\Temp\\" . uniqid() . ".php";
    file_put_contents($random_file, $php_code);
    $command = "php $random_file";
    $descriptorspec = array(
        0 => array("pipe", "r"),  // stdin
        1 => array("pipe", "w"),  // stdout
        2 => array("pipe", "w")   // stderr
    );
    $process = proc_open($command, $descriptorspec, $pipes);
    if (is_resource($process)) {
        $stdOut = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stdErr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);
    }
    unlink($random_file);
    echo ("done!\n"); // display just the main argument
    if ($stdOut == "" && $stdErr == "" && $exitCode == 0) {
        $stdOut = "Clean exit, no output.";
    }
    if ($stdErr == "") $stdErr = $exitCode == 0 ? "No errors." : "Error (ash): Process exited with non-zero exit code.";
    $result = ["stdout" => $stdOut, "stderr" => $sdtErr, "exit_code" => $exitCode];
    if ($this->ash->debug) echo ("debug: php_code() result: " . print_r($result, true) . "\n");
    return $result;
};
