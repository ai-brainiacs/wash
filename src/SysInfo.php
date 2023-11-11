<?php

namespace Rpurinton\Ash;

class SysInfo
{
    private $sysInfo = [];

    public function __construct(private $ash)
    {
        $this->refresh();
        if ($ash->debug) echo "(ash) sysInfo: " . print_r($this->sysInfo, true) . "\n";
    }

    public function __toArray()
    {
        return $this->sysInfo;
    }

    public function refresh()
    {
        $this->sysInfo = [
            'release' => trim(shell_exec("cat /etc/*release*")),
            'uname-a' => trim(shell_exec("uname -a")),
            'hostFQDN' => trim(shell_exec("hostname")),
            'hostName' => trim(shell_exec("hostname -s")),
            'ipAddr' => trim(shell_exec("ip addr | grep inet")),
            'etcHosts' => trim(shell_exec("cat /etc/hosts")),
            'uptime' => trim(shell_exec("uptime")),
            'free-mh' => trim(shell_exec("free -mh")),
            'df-h' => trim(shell_exec("df -h")),
            'who-u' => trim(shell_exec("who -u")),
            'termColorSupport' => $this->ash->config['colorSupport'] ? "yes" : "no",
            'termEmojiSupport' => $this->ash->config['emojiSupport'] ? "✅" : "no",
            'currentDate' => trim(shell_exec("date")),
            'userId' => trim(shell_exec("whoami")),
            'homeDir' => trim(shell_exec("echo ~")),
            'lastDir' => isset($this->sysInfo['lastDir']) ? $this->sysInfo['lastDir'] : trim(shell_exec("pwd")),
            'workingDir' => trim(shell_exec("pwd")),
        ];
        $this->sysInfo['workingFolder'] = basename($this->sysInfo['workingDir'] == "" ? "/" : basename($this->sysInfo['workingDir']));
        if ($this->sysInfo['workingDir'] == $this->sysInfo['homeDir']) $this->sysInfo['workingFolder'] = "~";
    }

    public function setLastDir($dir)
    {
        $this->sysInfo['lastDir'] = $dir;
    }
}
