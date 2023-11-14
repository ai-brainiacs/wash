<?php
$this->functionHandlers['proc_open'] = function ($args) {
    if ($this->ash->debug) echo ("debug: proc_open(" . print_r($args, true) . ")\n");
    $procExec = function ($input): array {
        if ($this->ash->debug) echo ("debug: proc_exec(" . print_r($input, true) . ")\n");
        $command = $input['command'] ?? "<not specified?>";
        echo ("$ " . $command . "\n"); // display just the main argument
        if ($command == "<not specified?>") return [
            "stdout" => "",
            "stderr" => "Error (ash): Missing required fields (command).",
            "exitCode" => -1,
        ];
        if ($this->ash->debug) echo ("debug: proc_exec() env: " . print_r($input['env'], true) . "\n");
        $descriptorspec = [
            0 => ["pipe", "r"], // stdin
            1 => ["pipe", "w"], // stdout
            2 => ["pipe", "w"], // stderr
        ];
        $pipes = [];
        try {
            $this->runningProcess = proc_open($input['command'], $descriptorspec, $pipes, $input['cwd'] ?? $this->ash->sysInfo->sysInfo['workingDir']);
        } catch (\Exception $e) {
            return [
                "stdout" => "",
                "stderr" => "Error (ash): proc_open() failed: " . $e->getMessage(),
                "exit_code" => -1,
            ];
        }
        if (is_resource($this->runningProcess)) {
            $stdout = stream_get_contents($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[0]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $exitCode = proc_close($this->runningProcess);
            $this->runningProcess = null;
            if ($stdout == "" && $stderr == "" && $exitCode == 0) {
                $stdout = "Clean exit, no output.";
            }
            if ($stderr == "") $stderr = $exitCode == 0 ? "No errors." : "Error (ash): Process exited with non-zero exit code.";
            $result = [
                "stdout" => $stdout,
                "stderr" => $stderr,
                "exitCode" => $exitCode,
            ];
            if ($this->ash->debug) echo ("debug: proc_exec() result: " . print_r($result, true) . "\n");
            return $result;
        }
        return [
            "stdout" => "",
            "stderr" => "Error (ash): proc_open() failed.",
            "exitCode" => -1,
        ];
    };
    $result = $procExec($args);
    if ($this->ash->debug) echo ("debug: proc_open() result: " . print_r($result, true) . "\n");
    return $result;
};
