<?php

class FaktoryClient
{
    private $faktoryHost;
    private $faktoryPort;
    private $faktoryPassword;
    private $worker;

    public function __construct($host = "localhost", $port = 7419, $password = null)
    {
        $this->faktoryHost = $host;
        $this->faktoryPort = $port;
        $this->faktoryPassword = $password;
        $this->worker = null;
    }

    public function setWorker($worker)
    {
        $this->worker = $worker;
    }

    public function push($job)
    {
        $socket = $this->connect();
        $this->writeLine($socket, 'PUSH', json_encode($job));
        $this->close($socket);
    }

    public function fetch($queues = array('default'))
    {
        $socket = $this->connect();
        $response = $this->writeLine($socket, 'FETCH', implode(' ', $queues));
        $char = $response[0];
        if ($char === '$') {
            $count = trim(substr($response, 1, strpos($response, "\r\n")));
            $data = null;
            if ($count > 0) {
                $data = $this->readLine($socket);
                $this->close($socket);
                return json_decode($data, true);
            }
            return $data;
        }
        $this->close($socket);
        return $response;
    }

    public function ack($jobId)
    {
        $socket = $this->connect();
        $this->writeLine($socket, 'ACK', json_encode(['jid' => $jobId]));
        $this->close($socket);
    }

    public function fail($jobId)
    {
        $socket = $this->connect();
        $this->writeLine($socket, 'FAIL', json_encode(['jid' => $jobId]));
        $this->close($socket);
    }

    private function connect()
    {
        $socket = stream_socket_client("tcp://{$this->faktoryHost}:{$this->faktoryPort}", $errno, $errstr, 30);
        if (!$socket) {
            echo "$errstr ($errno)\n";
            return false;
        } else {
            $response = $this->readLine($socket);

            $requestDefaults = [
                'v' => 2
            ];

            // If the client is a worker, send the wid with request
            if ($this->worker) {
                $requestDefaults = array_merge(['wid' => $this->worker->getID()], $requestDefaults);
            }

            if (strpos($response, "\"s\":") !== false && strpos($response, "\"i\":") !== false) {
                // Requires password
                if (!$this->faktoryPassword) {
                    throw new \Exception('Password is required.');
                }

                $payloadArray = json_decode(substr($response, strpos($response, '{')));

                $authData = $this->faktoryPassword . $payloadArray->s;
                for ($i = 0; $i < $payloadArray->i; $i++) {
                    $authData = hash('sha256', $authData, true);
                }

                $requestWithPassword = json_encode(array_merge(['pwdhash' => bin2hex($authData)], $requestDefaults));
                $responseWithPassword = $this->writeLine($socket, 'HELLO', $requestWithPassword);
                if (strpos($responseWithPassword, "ERR Invalid password")) {
                    throw new \Exception('Password is incorrect.');
                }

            } else {
                // Doesn't require password
                if ($response !== "+HI {\"v\":2}\r\n") {
                    throw new \Exception('Hi not received :(');
                }

                $this->writeLine($socket, 'HELLO', json_encode($requestDefaults));
            }
            return $socket;
        }
    }

    private function readLine($socket)
    {
        $contents = fgets($socket, 1024);
        while (strpos($contents, "\r\n") === false) {
            $contents .= fgets($socket, 1024 - strlen($contents));
        }
        return $contents;
    }

    private function writeLine($socket, $command, $json)
    {
        $buffer = $command . ' ' . $json . "\r\n";
        stream_socket_sendto($socket, $buffer);
        $read = $this->readLine($socket);
        return $read;
    }

    private function close($socket)
    {
        fclose($socket);
    }
}