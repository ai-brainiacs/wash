<?php

namespace Rpurinton\Ash;

class OpenAI
{
    public $client = null;
    public $models = [];
    public $model = null;
    public $maxTokens = null;
    public $basePrompt = null;
    public $baseTokens = 0;
    public $runningProcess = null;
    public $util = null;
    public $history = null;
    public $functionHandlers = [];
    public $modelPicker = null;

    public function __construct(public $ash)
    {
        $this->util = new Util();
        $this->history = new History($this->util, $this->ash);
        $this->client = \OpenAI::client($this->ash->config->config['openaiApiKey']);
        $models = $this->client->models()->list()->data;
        foreach ($models as $model) if (mb_substr($model->id, 0, 3) == 'gpt') $this->models[] = $model->id;
        $this->modelPicker = new ModelPicker($this);
        $this->modelPicker->selectModel();
        $this->modelPicker->selectMaxTokens();
        if (!$ash->debug) passthru("clear");
        $this->basePrompt = file_get_contents(__DIR__ . "/base_prompt.txt");
        $this->baseTokens = $this->util->tokenCount($this->basePrompt);
        $this->welcomeMessage();
    }

    public function __destruct()
    {
        if ($this->runningProcess) proc_terminate($this->runningProcess);
    }

    public function welcomeMessage()
    {
        $this->history->saveMessage(["role" => "system", "content" => "User started a new ash session from : " . $this->ash->sysInfo->sysInfo["who-u"] . "\n Please greet them!"]);
        $messages = $this->buildPrompt();
        $messages[] = ["role" => "system", "content" => "Run any initial functions you need to then review everything for any potential areas that may need attention, and finally write a short welcome message to the user with a brief assessment of server status/health.\n"];
        $this->handlePromptAndResponse($messages);
    }

    public function userMessage($input)
    {
        $this->history->saveMessage(["role" => "user", "content" => $input]);
        $this->handlePromptAndResponse($this->buildPrompt());
    }

    private function buildPrompt()
    {
        $dynamic_prompt = "SYSTEM: Your full name is " . $this->ash->sysInfo->sysInfo['hostFQDN'] . ", but people can call you " . $this->ash->sysInfo->sysInfo['hostName'] . " for short.\n";
        $dynamic_prompt .= "SYSTEM: Current System Info: " . print_r($this->ash->sysInfo->sysInfo, true);
        $dynamic_prompt .= "SYSTEM: " . ($this->ash->config->config['emojiSupport'] ? "Emoji support enabled!  Use it to express yourself!  🤣🤣🤣\n" : "Emoji support disabled. Do not send emoji!\n");
        $dynamic_prompt .= "SYSTEM: " . ($this->ash->config->config['colorSupport'] ? "Instead of Markdown, Use ANSI escape codes to add more style and emphasis to all your outputs including combinations of \e[95mcolors\e[0m, \e[1mbold\e[0m, \e[3mitalic\e[0m, \e[4munderline\e[0m, \e[9mstrikethrough\e[0m, \e[7minverted\e[0m, and \e[4;9;7mcombinations\e[0m!\nAlways prefer the 'light' variant like 'light blue' over 'blue' to ensure maximum compatibility with all terminal color schemes.\n" : "Terminal color support disabled. Do not send ANSI color/style codes!\n");
        $dynamic_prompt .= "SYSTEM: Please do as much as you can to make sure the user has a good experience.  Take the lead in running any functions that may be needed, without prompting the user too much.  Assume they may be a novice.\n";
        $dynamic_prompt .= "SYSTEM: If problems arise, please iterate on the issue.  Example, update config, restart service, check logs, update the config again, restart the service again, check the logs again, etc. Until it gets resolved or until you have exhausted all options.\n";
        $dynamic_prompt .= "SYSTEM: Please use the last USER message text before this as your primary point of context.";
        $dynamic_prompt .= "ASSISTANT: ";
        $dynamic_tokens = $this->util->tokenCount($dynamic_prompt);
        $messages[] = ["role" => "system", "content" => $this->basePrompt];
        $response_space = round($this->maxTokens * 0.1, 0);
        $history_space = $this->maxTokens - $this->baseTokens - $dynamic_tokens - $response_space;
        $messages = array_merge($messages, $this->history->getHistory($history_space));
        $messages[] = ["role" => "system", "content" => $dynamic_prompt];
        return $messages;
    }

    private function handlePromptAndResponse($messages)
    {
        $prompt = [
            "model" => $this->model,
            "messages" => $messages,
            "temperature" => 0.1,
            "top_p" => 0.1,
            "frequency_penalty" => 0.0,
            "presence_penalty" => 0.0,
            "functions" => $this->getFunctions(),
        ];
        if ($this->ash->debug) echo ("debug: Sending prompt to OpenAI: " . print_r($prompt, true) . "\n");
        if (!$this->ash->config->config["emojiSupport"]) echo ("Thinking...");
        else echo ("🧠 Thinking...");

        try {
            $stream = $this->client->chat()->createStreamed($prompt);
        } catch (\Exception $e) {
            if ($this->ash->debug) echo ("debug: Error: " . print_r($e, true) . "\n");
            else echo ("Error: " . $e->getMessage() . "\n");
            return;
        } catch (\Error $e) {
            if ($this->ash->debug) echo ("debug: Error: " . print_r($e, true) . "\n");
            else echo ("Error: " . $e->getMessage() . "\n");
            return;
        } catch (\Throwable $e) {
            if ($this->ash->debug) echo ("debug: Error: " . print_r($e, true) . "\n");
            else echo ("Error: " . $e->getMessage() . "\n");
            return;
        }
        $this->handleStream($stream);
    }

    private function handleStream($stream)
    {
        $function_call = null;
        $full_response = "";
        $line = "";
        $status_ptr = 0;
        $status_chars = ["|", "/", "-", "\\"];
        $first_sent = false;
        try {
            foreach ($stream as $response) {
                $reply = $response->choices[0]->toArray();
                $finish_reason = $reply["finish_reason"];
                if (isset($reply["delta"]["function_call"]["name"])) {
                    $function_call = $reply["delta"]["function_call"]["name"];
                    $functionNameDisplay = str_replace("_", " ", $function_call);
                    echo ("\r✅ Running $functionNameDisplay... %");
                }
                if ($function_call) {
                    if (isset($reply["delta"]["function_call"]["arguments"])) {
                        $status_ptr++;
                        if ($status_ptr > 3) $status_ptr = 0;
                        echo ("\r✅ Running $functionNameDisplay... " . $status_chars[$status_ptr]);
                        $full_response .= $reply["delta"]["function_call"]["arguments"];
                    }
                } else if (isset($reply["delta"]["content"])) {
                    if (!$first_sent) {
                        $first_sent = true;
                        echo ("\r                       \r");
                    }
                    $delta_content = $reply["delta"]["content"];
                    $full_response .= $delta_content;
                    $line .= $delta_content;
                    $line_break_pos = mb_strrpos($line, "\n");
                    if ($line_break_pos !== false) {
                        $output = mb_substr($line, 0, $line_break_pos);
                        $line = mb_substr($line, $line_break_pos + 1);
                        $output = str_replace("\n", "\n", $output);
                        $output = str_replace("\\e", "\e", $output);
                        $output = $this->util->markdownToEscapeCodes($output, $this->ash->config->config['colorSupport']);
                        echo ("$output\n");
                    } else {
                        if (mb_strlen($line) > $this->ash->sysInfo->sysInfo['terminalColumns']) {
                            $wrapped_text = wordwrap($line, $this->ash->sysInfo->sysInfo['terminalColumns'], "\n", true);
                            $line_break_pos = mb_strrpos($wrapped_text, "\n");
                            $output = mb_substr($wrapped_text, 0, $line_break_pos);
                            $line = mb_substr($wrapped_text, $line_break_pos + 1);
                            $output = str_replace("\n", "\n", $output);
                            $output = str_replace("\\e", "\e", $output);
                            $output = $this->util->markdownToEscapeCodes($output, $this->ash->config->config['colorSupport']);
                            echo ("$output\n");
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            if ($this->ash->debug) echo ("debug: Error: " . print_r($e, true) . "\n");
            else echo ("Error: " . $e->getMessage() . "\n");
            return;
        } catch (\Error $e) {
            if ($this->ash->debug) echo ("debug: Error: " . print_r($e, true) . "\n");
            else echo ("Error: " . $e->getMessage() . "\n");
            return;
        } catch (\Throwable $e) {
            if ($this->ash->debug) echo ("debug: Error: " . print_r($e, true) . "\n");
            else echo ("Error: " . $e->getMessage() . "\n");
            return;
        }

        if ($function_call) {
            $arguments = json_decode($full_response, true);
            $this->handleFunctionCall($function_call, $arguments);
        } else {
            if ($line != "") {
                $output = wordwrap($line, $this->ash->sysInfo->sysInfo['terminalColumns'], "\n", true);
                $output = str_replace("\n", "\n", $output);
                $output = str_replace("\\e", "\e", $output);
                $output = $this->util->markdownToEscapeCodes($output, $this->ash->config->config['colorSupport']);
                echo trim($output) . "\n";
            }
            $assistant_message = ["role" => "assistant", "content" => $full_response];
            $this->history->saveMessage($assistant_message);
        }
        if ($this->ash->debug) {
            if ($function_call) echo ("✅ Response complete.  Function call: " . print_r($arguments, true) . "\n");
            else echo ("Response complete.\n");
        }
    }

    private function handleFunctionCall($function_call, $arguments)
    {
        if ($this->ash->debug) echo ("debug: handleFunctionCall($function_call, " . print_r($arguments, true) . ")\n");
        $function_message = ["role" => "assistant", "content" => null, "function_call" => ["name" => $function_call, "arguments" => json_encode($arguments)]];
        $this->history->saveMessage($function_message);
        if (isset($this->functionHandlers[$function_call])) {
            $handler = $this->functionHandlers[$function_call];
            $result = $handler($arguments);
            if ($this->ash->debug) echo ("debug: handleFunctionCall($function_call, " . print_r($arguments, true) . ") result: " . print_r($result, true) . "\n");
            $this->functionFollowUp($function_call, $result);
            return;
        } else $this->functionFollowUp($function_call, ["stdout" => "", "stderr" => "Error (ash): function handler for $function_call not found.  Does ash/src/functions.d/$function_call.php exist?", "exitCode" => -1]);
        return;
    }

    private function functionFollowUp($function_call, $result)
    {
        $function_result = ["role" => "function", "name" => $function_call, "content" => json_encode($result)];
        $this->history->saveMessage($function_result);
        $this->handlePromptAndResponse($this->buildPrompt());
    }

    private function getFunctions()
    {
        exec('ls ' . __DIR__ . '/functions.d/*.json', $functions);
        $result = [];
        foreach ($functions as $function) {
            $result[] = json_decode(file_get_contents($function), true);
            $handlerFile = str_replace(".json", ".php", $function);
            if (file_exists($handlerFile)) {
                include($handlerFile);
            }
        }
        return $result;
    }
}
