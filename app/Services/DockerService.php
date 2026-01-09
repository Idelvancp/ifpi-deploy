<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;

class DockerService
{
    protected $client;
    protected $socketPath;

    public function __construct()
    {
        $this->socketPath = '/var/run/docker.sock';
        
        // Configurar cliente Guzzle para usar socket Unix
        $this->client = new Client([
            'base_uri' => 'http://localhost',
            'curl' => [
                CURLOPT_UNIX_SOCKET_PATH => $this->socketPath
            ],
            'timeout' => 30,
            'http_errors' => false
        ]);
    }

    /**
     * Listar containers
     */
    public function listContainers(bool $all = true): array
{
    try {
        $response = $this->client->get('/containers/json', [
            'query' => [
                'all' => $all ? '1' : '0'
            ]
        ]);

        if ($response->getStatusCode() === 200) {
            return json_decode($response->getBody()->getContents(), true);
        }

        Log::error('Docker API Error: ' . $response->getStatusCode() . ' - ' . $response->getBody());
        return [];

    } catch (Exception $e) {
        Log::error('Docker Connection Error: ' . $e->getMessage());
        return [];
    }
}


    /**
     * Criar um novo container
     */
    public function createContainer(array $config): array
    {
        try {
            $response = $this->client->post('/containers/create', [
                'json' => $config,
                'headers' => ['Content-Type' => 'application/json']
            ]);

            if ($response->getStatusCode() === 201) {
                return json_decode($response->getBody()->getContents(), true);
            }

            throw new Exception('Failed to create container: ' . $response->getStatusCode() . ' - ' . $response->getBody());

        } catch (Exception $e) {
            Log::error('Docker Create Error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Iniciar um container
     */
    public function startContainer(string $containerId): bool
    {
        try {
            $response = $this->client->post("/containers/{$containerId}/start");
            return $response->getStatusCode() === 204;

        } catch (Exception $e) {
            Log::error('Docker Start Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Parar um container
     */
    public function stopContainer(string $containerId): bool
    {
        try {
            $response = $this->client->post("/containers/{$containerId}/stop");
            return $response->getStatusCode() === 204;

        } catch (Exception $e) {
            Log::error('Docker Stop Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Remover um container
     */
    public function removeContainer(string $containerId, bool $force = false): bool
    {
        try {
            $response = $this->client->delete("/containers/{$containerId}", [
                'query' => ['force' => $force]
            ]);
            return $response->getStatusCode() === 204;

        } catch (Exception $e) {
            Log::error('Docker Remove Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Obter informações de um container
     */
    public function getContainerInfo(string $containerId): array
    {
        try {
            $response = $this->client->get("/containers/{$containerId}/json");

            if ($response->getStatusCode() === 200) {
                return json_decode($response->getBody()->getContents(), true);
            }

            Log::error('Docker Info Error: ' . $response->getStatusCode() . ' - ' . $response->getBody());
            return [];

        } catch (Exception $e) {
            Log::error('Docker Info Error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Obter logs de um container
     */
    public function getContainerLogs(string $containerId): string
    {
        try {
            $response = $this->client->get("/containers/{$containerId}/logs", [
                'query' => [
                    'stdout' => 'true',
                    'stderr' => 'true',
                    'timestamps' => 'true'
                ]
            ]);

            if ($response->getStatusCode() === 200) {
                return $response->getBody()->getContents();
            }

            Log::error('Docker Logs Error: ' . $response->getStatusCode() . ' - ' . $response->getBody());
            return '';

        } catch (Exception $e) {
            Log::error('Docker Logs Error: ' . $e->getMessage());
            return '';
        }
    }

    /**
     * Listar imagens disponíveis
     */
    public function listImages(): array
    {
        try {
            $response = $this->client->get('/images/json');

            if ($response->getStatusCode() === 200) {
                return json_decode($response->getBody()->getContents(), true);
            }

            Log::error('Docker Images Error: ' . $response->getStatusCode() . ' - ' . $response->getBody());
            return [];

        } catch (Exception $e) {
            Log::error('Docker Images Error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Verificar se Docker está rodando
     */
    public function checkDocker(): bool
    {
        try {
            $response = $this->client->get('/_ping');
            return $response->getStatusCode() === 200 && $response->getBody()->getContents() === 'OK';
            
        } catch (Exception $e) {
            Log::error('Docker Ping Error: ' . $e->getMessage());
            return false;
        }
    }
}   