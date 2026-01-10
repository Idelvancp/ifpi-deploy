<?php

namespace App\Http\Controllers;

use App\Services\DockerService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class DockerController extends Controller
{
    protected $dockerService;

    public function __construct(DockerService $dockerService)
    {
        $this->dockerService = $dockerService;
    }

    /**
     * Listar todos os containers
     */
    public function index(): JsonResponse
    {
        $user = Auth::user();
        $matricula = (string) $user->student_id;
        $command = "curl --silent --unix-socket /var/run/docker.sock http://localhost/containers/json?all=1";
        $output = shell_exec($command);
        // Se não conseguir decodificar, devolve array vazio
        $containers = json_decode($output, true) ?? [];


        // Garante que sempre retorna um array
        if (!is_array($containers)) {
            $containers = [];
        }

        // 🔎 Filtra apenas containers que contenham a matrícula no nome
        $containersFiltrados = array_values(array_filter($containers, function ($container) use ($matricula) {
            if (!isset($container['Names']) || !is_array($container['Names'])) {
                return false;
            }
       
            foreach ($container['Names'] as $name) {
                // remove a "/" inicial do Docker
                $name = ltrim($name, '/');

                // ex: app-django-3129197
                if (str_contains($name, $matricula)) {
                    
                    return true;
                }
            }

            return false;
        }));

        return response()->json($containersFiltrados);
    }

    /**
     * Criar um novo container
     */
    public function create(Request $request): JsonResponse
    {
        $user = auth()->user(); // aluno logado
        $uuid = (string) $user->student_id;

        $homeBase = config('students.homes_path');
        $homePath = $homeBase . '/' . $uuid;

        if (!is_dir($homePath)) {
            mkdir($homePath, 0750, true);
        }

        $command = <<<BASH
        curl --silent --unix-socket /var/run/docker.sock \
        http://localhost/containers/json?all=1 \
        | jq -r '
        .[]
        | select(.Ports != null)
        | .Ports[]
        | select(has("PublicPort"))
        | .PublicPort
        '
        BASH;

        $output = shell_exec($command);
        $ports = array_filter(
                    array_map('intval', explode("\n", trim($output)))
                );           
        $allPorts = range(49152, 65535);
        $usedPorts = array_intersect($ports, $allPorts);
        $freePorts = array_values(array_diff($allPorts, $usedPorts));

        if (empty($freePorts)) {
            throw new \RuntimeException('Nenhuma porta disponível');
        }

        $randomPort = $freePorts[array_rand($freePorts)];
        $containerName = $uuid . '-' . $randomPort;

        $create = <<<JSON
        {
        "Image": "django-image",
        "HostConfig": {
            "Binds": [
            "/srv/student-homes/$uuid/$containerName/app:/app"
            ],
            "PortBindings": {
            "8000/tcp": [
                { "HostPort": "$randomPort" }
            ]
            }
        },
        "ExposedPorts": {
            "8000/tcp": {}
        }
        }
        JSON;


        $command = "curl --silent --unix-socket /var/run/docker.sock \
                    -X POST -H 'Content-Type: application/json' \
                    http://localhost/containers/create?name=$containerName \
                    -d '$create'";

        $response = shell_exec($command);
        $data = json_decode($response, true);
        $id = $data["Id"];
        
        shell_exec("curl --silent --unix-socket /var/run/docker.sock -X POST http://localhost/containers/$id/start");


        return response()->json([

            'message' => match (204) {
                '204' => 'Container iniciado com sucesso',
                '304' => 'Container já estava em execução',
                '404' => 'Container não encontrado',
                default => 'Faliniciar container'
            }
        ]);
    }

    /**
     * Iniciar um container
     */
    public function start(string $containerId): JsonResponse
    {
        $command = sprintf(
            'curl --silent --output /dev/null --write-out "%%{http_code}" ' .
            '--unix-socket /var/run/docker.sock -X POST http://localhost/containers/%s/start',
            escapeshellarg($containerId)
        );

        $httpCode = exec($command, $output, $exitCode);

        $success = ($exitCode === 0 && in_array($httpCode, ['204', '304']));

        return response()->json([
            'success' => $success,
            'http_code' => $httpCode,
            'exit_code' => $exitCode,
            'message' => match ($httpCode) {
                '204' => 'Container iniciado com sucesso',
                '304' => 'Container já estava em execução',
                '404' => 'Container não encontrado',
                default => 'Falha ao iniciar container'
            }
        ]);
    }

    /**
     * Parar um container
     */
    public function stop(string $containerId): JsonResponse
    {
        $command = sprintf(
            'curl --silent --output /dev/null --write-out "%%{http_code}" ' .
            '--unix-socket /var/run/docker.sock -X POST ' .
            'http://localhost/containers/%s/stop?t=10',
            escapeshellarg($containerId)
        );

       $httpCode = exec($command, $output, $exitCode);

        $success = ($exitCode === 0 && in_array($httpCode, ['204', '304']));

        return response()->json([
            'success' => $success,
            'http_code' => $httpCode,
            'exit_code' => $exitCode,
            'message' => match ($httpCode) {
                '204' => 'Container parado com sucesso',
                '304' => 'Container já estava parado',
                '404' => 'Container não encontrado',
                '500' => 'Erro interno do Docker ao parar o container',
                default => 'Falha ao parar o container'
            }
        ]);
    }

    /**
     * Remover um container
     */
    public function destroy(string $containerId): JsonResponse
    {
        $command = sprintf(
            'curl --silent --output /dev/null --write-out "%%{http_code}" ' .
            '--unix-socket /var/run/docker.sock -X DELETE ' .
            'http://localhost/containers/%s?force=true&v=true',
            escapeshellarg($containerId)
        );

        $httpCode = exec($command, $output, $exitCode);

        $success = ($exitCode === 0 && in_array($httpCode, ['204']));

        return response()->json([
            'success'    => $success,
            'http_code' => $httpCode,
            'exit_code' => $exitCode,
            'message'   => match ($httpCode) {
                '204' => 'Container removido com sucesso',
                '404' => 'Container não encontrado',
                '409' => 'Container em uso (não foi possível remover)',
                '500' => 'Erro interno do Docker ao remover o container',
                default => 'Falha ao remover o container'
            }
        ]);
    }


    /**
     * Obter informações de um container
     */
    public function show(string $containerId): JsonResponse
    {
        $info = $this->dockerService->getContainerInfo($containerId);
        
        return response()->json([
            'success' => !empty($info),
            'data' => $info
        ]);
    }

    /**
     * Obter logs de um container
     */
    public function logs(string $containerId): JsonResponse
    {
        $logs = $this->dockerService->getContainerLogs($containerId);
        
        return response()->json([
            'success' => !empty($logs),
            'logs' => $logs
        ]);
    }

    /**
     * Verificar status do Docker
     */
    public function status(): JsonResponse
    {
        $isRunning = $this->dockerService->checkDocker();
        
        return response()->json([
            'success' => $isRunning,
            'docker_running' => $isRunning,
            'message' => $isRunning ? 'Docker is running' : 'Docker is not available'
        ]);
    }
}
