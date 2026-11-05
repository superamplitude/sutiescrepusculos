# Atendimento integrado — Suítes Crepúsculo

Adaptador de servidor para reutilizar a infraestrutura já existente: voz natural da Central de Comunicação, automação de respostas no CRM/Chatwoot e chamadas WhatsApp por WaCalls. Não cria outro runner e não altera os demais projetos.

## Ativação no servidor
Node.js 20+; executar apenas pelo runner Gerente-VPS já autorizado. O servidor escuta **somente** em 127.0.0.1 e requer porta definida. O proxy HTTPS e as rotas de autenticação devem ser ligados à aplicação do Crepúsculo antes de expor a interface. Não usar rotas/portas adicionais sem autorização existente.

Variáveis obrigatórias para ativar cada serviço (injetadas via gerenciador de segredos da VPS, nunca versionadas):
- `SUITES_ATTENDANCE_PORT` e `SUITES_ATTENDANCE_TOKEN` (mínimo 32 caracteres) para o backend local.
- `CENTRAL_COMMUNICATION_API_URL`, `CENTRAL_COMMUNICATION_SUITES_TOKEN`, `SUITES_GOOGLE_API_KEY` para voz. O servidor central precisa registrar o projeto `suites-crepusculo` e o hash do token antes da chamada.
- `CHATWOOT_API_URL`, `CHATWOOT_SUITES_ACCOUNT_ID`, `CHATWOOT_SUITES_ACCESS_TOKEN` para respostas. Deve ser uma Account e inbox próprios, vinculados ao número WhatsApp autorizado.
- `CRM_SUITES_REPLY_ENDPOINT`, `CRM_SUITES_REPLY_TOKEN`, `CHATWOOT_SUITES_WEBHOOK_SECRET` para eventos de mensagens recebidas. O remetente precisa encaminhar o webhook com cabeçalho `X-Suites-Signature: sha256=<HMAC SHA-256>` do corpo; o Chatwoot não é presumido compatível diretamente com esse contrato. Configurar no conector existente, sem duplicar respostas.
- `WACALLS_API_BASE_URL`, `WACALLS_API_TOKEN`, `SUITES_WACALLS_SESSION_ID` para iniciar chamadas consentidas. Recepção/atendimento de chamadas e áudio bidirecional exigem integração de eventos e mídia do provedor, **não implementados apenas por este adaptador**.

Rotas internas: `GET /health`, `POST /voice/speech`, `POST /whatsapp/reply`, `POST /whatsapp/webhook`, `POST /calls/start`.

**Estado:** código integrado ao repositório, mas não publicado nem habilitado na VPS. Sem credenciais, números/sessões do projeto, vínculo com a página PHP e fluxo de chamadas recebidas, não existe atendimento funcional. Não declarar produção aprovada antes de testar mensagem recebida/respondida, voz reproduzida, chamada recebida e chamada de saída consentida.

Este componente não realiza backups, mudanças no banco, exclusões ou alterações no CRM/AEL.
