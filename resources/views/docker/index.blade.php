<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Docker Containers</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="p-4">
    <div class="container">
        <h1 class="mb-4">Gerenciamento de Containers</h1>

        <button id="listarBtn" class="btn btn-primary mb-3">Listar Containers</button>
        <button class="btn btn-primary mb-3" onclick="criarAmbiente()">Deploy</button>
        <input type="file" id="arquivoProjeto" accept=".zip" style="display:none">

        <table class="table table-striped align-middle" id="containersTable" style="display:none;">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nome</th>
                    <th>Imagem</th>
                    <th>Estado</th>
                    <th>Status</th>
                    <th class="text-center">Ações</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>

        <div id="mensagem" class="alert alert-info" style="display:none;"></div>
    </div>

    <script>
    document.getElementById('listarBtn').addEventListener('click', listarContainers);
    const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    function criarAmbiente() {
        fetch('/api/docker/container/create', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': token,
                'Content-Type': 'application/json'
            }
        })
        .then(res => res.json())
        .then(data => {
            alert(data.message);
            listarContainers(); // reaproveita seu código
        })
        .catch(err => alert("Erro: " + err));
    }



    function listarContainers() {
        fetch("{{ route('docker.containers') }}")
            .then(response => response.json())
            .then(data => {
                const tabela = document.getElementById('containersTable');
                const corpo = tabela.querySelector('tbody');
                corpo.innerHTML = "";

                if (!Array.isArray(data) || data.length === 0) {
                    document.getElementById('mensagem').style.display = "block";
                    document.getElementById('mensagem').textContent = "Nenhum container encontrado.";
                    tabela.style.display = "none";
                    return;
                }

                data.forEach(container => {
                    let idCurto = container.Id.substring(0,12);
                    let nome = container.Names ? container.Names[0].replace("/", "") : "-";

                    let porta = null;
                    if (Array.isArray(container.Ports)) {
                        let portaPublica = container.Ports.find(p => p.PublicPort);
                        if (portaPublica) {
                            porta = portaPublica.PublicPort;
                        }
                    }

                    // 🔗 botão acessar
                    let botaoAcessar = porta
                        ? `<a href="http://localhost:${porta}" target="_blank"
                            class="btn btn-sm btn-primary me-1"
                            title="Acessar aplicação">
                            🚀
                        </a>`
                        : `<button class="btn btn-sm btn-secondary me-1" disabled title="Sem porta publicada">🚫</button>`;


                    let row = `
                        <tr>
                            <td>${idCurto}</td>
                            <td>${nome}</td>
                            <td>${container.Image}</td>
                            <td>${container.State}</td>
                            <td>${container.Status}</td>
                            <td class="text-center">
                                ${botaoAcessar}
                                <button class="btn btn-sm btn-info me-1" onclick="selecionarArquivo('${idCurto}', '${nome}')">Upload</button>
                                <button class="btn btn-sm btn-warning me-1" onclick="pararContainer('${idCurto}')">Parar</button>
                                <button class="btn btn-sm btn-success me-1" onclick="iniciarContainer('${idCurto}')">Iniciar</button>
                                <button class="btn btn-sm btn-danger" onclick="deletarContainer('${idCurto}', ${porta !== null ? porta : 'null'})">Excluir</button>
                            </td>
                        </tr>
                    `;
                    corpo.insertAdjacentHTML("beforeend", row);
                });

                tabela.style.display = "table";
                document.getElementById('mensagem').style.display = "none";
            })
            .catch(error => {
                document.getElementById('mensagem').style.display = "block";
                document.getElementById('mensagem').textContent = "Erro: " + error;
            });
    }

    // --- Ações ---
    function pararContainer(id) {
        if (!confirm("Deseja parar o container " + id + "?")) return;

        fetch(`/api/docker/container/${id}/stop`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': token,
                'Content-Type': 'application/json'
            }
        })
        .then(response => response.json())
        .then(result => {
            alert(result.message);
            listarContainers();
        })
        .catch(err => alert("Erro ao parar o container: " + err));
    }

    function iniciarContainer(id) {
        if (!confirm("Deseja iniciar o container " + id + "?")) return;

        fetch(`/api/docker/container/${id}/start`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': token,
                'Content-Type': 'application/json'
            }
        })
        .then(response => response.json())
        .then(result => {
            alert(result.message);
            listarContainers();
        })
        .catch(err => alert("Erro ao iniciar o container: " + err));
    }

    function deletarContainer(id, porta) {
        fetch(`/api/docker/container/${id}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': token,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                porta: porta ?? null
            })
        })
        .then(async response => {
            if (!response.ok) {
                throw new Error(`Erro HTTP ${response.status}`);
            }

            // Se for 204, não há body
            if (response.status === 204) {
                return { message: 'Container removido com sucesso' };
            }

            return response.json();
        })
        .then(result => {
            alert(result.message);
            listarContainers();
        })
        .catch(err => {
            console.error(err);
            alert("Erro ao excluir o container");
        });
    }

    function selecionarArquivo(containerId, containerName) {
        const input = document.getElementById('arquivoProjeto');

        input.onchange = () => {
            uploadProjeto(containerId, containerName);
        };

        input.click(); 
    }

    function uploadProjeto(containerId, containerName) {
        const input = document.getElementById('arquivoProjeto');

        if (!input.files.length) {
            alert("Selecione um arquivo .zip primeiro!");
            return;
        }

        const file = input.files[0];

        // aceita somente zip
        if (!file.name.toLowerCase().endsWith('.zip')) {
            alert("Envie apenas arquivos .zip");
            input.value = "";
            return;
        }

        // limite frontend (200MB)
        const maxSize = 200 * 1024 * 1024;

        if (file.size > maxSize) {
            alert("Arquivo maior que 200MB.");
            input.value = "";
            return;
        }

        const formData = new FormData();
        formData.append("file", file);
        formData.append("containerName", containerName);

        fetch(`/api/docker/container/${containerId}/upload`, {
            method: "POST",
            headers: {
                "X-CSRF-TOKEN": token,
                "Accept": "application/json"
            },
            body: formData
        })
        .then(async res => {
            const text = await res.text();

            console.log("Resposta servidor:", text);

            // Detecta erro de tamanho vindo do PHP/Laravel
            if (
                text.includes("POST Content-Length") ||
                text.includes("The POST data is too large") ||
                text.includes("PostTooLargeException")
            ) {
                throw new Error("Arquivo excede o limite permitido no servidor.");
            }

            // erro HTTP comum
            if (!res.ok) {
                throw new Error(`HTTP ${res.status}`);
            }

            // tenta extrair JSON mesmo se vier HTML + JSON misturado
            const inicioJson = text.indexOf("{");

            if (inicioJson === -1) {
                throw new Error("Resposta inválida do servidor.");
            }

            const jsonLimpo = text.substring(inicioJson);

            try {
                return JSON.parse(jsonLimpo);
            } catch (e) {
                throw new Error("Não foi possível interpretar a resposta do servidor.");
            }
        })
        .then(data => {
            alert(data.message || "Upload realizado com sucesso!");
            input.value = "";
        })
        .catch(err => {
            console.error("Erro upload:", err);
            alert("Erro no upload: " + err.message);
            input.value = "";
        });
    }


    document.addEventListener("DOMContentLoaded", function () {
    listarContainers();
});

    </script>
</body>
</html>
