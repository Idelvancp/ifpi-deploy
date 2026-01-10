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
                                <button class="btn btn-sm btn-warning me-1" onclick="pararContainer('${idCurto}')">Parar</button>
                                <button class="btn btn-sm btn-success me-1" onclick="iniciarContainer('${idCurto}')">Iniciar</button>
                                <button class="btn btn-sm btn-danger" onclick="deletarContainer('${idCurto}')">Excluir</button>
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

    function deletarContainer(id) {
        if (!confirm("Deseja realmente excluir o container " + id + "?")) return;

        fetch(`/api/docker/container/${id}`, {
            method: 'DELETE',
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
        .catch(err => alert("Erro ao excluir o container: " + err));
    }

    document.addEventListener("DOMContentLoaded", function () {
    listarContainers();
});

    </script>
</body>
</html>
