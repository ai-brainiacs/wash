<?php

namespace Rpurinton\Ash;

class SysInfo
{
    public $sysInfo = [];

    public function __construct(private $ash)
    {
        $this->refresh();
        if ($ash->debug) echo "sysInfo: " . print_r($this->sysInfo, true) . "\n";
    }

    public function refresh()
    {
        $terminalSize = shell_exec('mode');
        preg_match('/Lines:\s+(\d+)/', $terminalSize, $lines);
        preg_match('/Columns:\s+(\d+)/', $terminalSize, $columns);

        $this->sysInfo = [
            'release' => php_uname(),
            'uname-a' => php_uname('a'),
            'hostFQDN' => gethostname(),
            'hostName' => gethostname(),
            'ram' => round(disk_free_space("/") / 1024 / 1024, 2) . 'M',
            'disk' => round(disk_total_space("/") / 1024 / 1024, 2) . 'M',
            'emergencyContact' => 'not set',
            'ashEmailAddress' => 'not set',
            'who-u' => get_current_user(),
            'termColorSupport' => $this->ash->config->config['colorSupport'] ? "\e[32myes\e[0m" : "no",
            'termEmojiSupport' => $this->ash->config->config['emojiSupport'] ? "✅" : "no",
            'terminalLines' => $lines[1] ?? "not set",
            'terminalColumns' => $columns[1] ?? "not set",
            'currentDate' => trim(shell_exec("date /T") ?? "not set"),
            'userId' => get_current_user(),
            'homeDir' => getenv('USERPROFILE'),
            'lastDir' => isset($this->sysInfo['lastDir']) ? $this->sysInfo['lastDir'] : getcwd(),
            'workingDir' => getcwd(),
        ];

        $this->sysInfo['workingFolder'] = basename($this->sysInfo['workingDir'] == "" ? "/" : basename($this->sysInfo['workingDir']));
        if ($this->sysInfo['workingDir'] == $this->sysInfo['homeDir']) $this->sysInfo['workingFolder'] = "~";
        if ($this->ash->config->config['emailAddress'] != "") $this->sysInfo['emergencyContact'] = $this->ash->config->config['emailAddress'];
        if ($this->ash->config->config['fromAddress'] != "") $this->sysInfo['ashEmailAddress'] = $this->ash->config->config['fromAddress'];
    }

    public function setLastDir($dir)
    {
        $this->sysInfo['lastDir'] = $dir;
    }
}
