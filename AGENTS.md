# Suítes Crepúsculo — continuidade

- Código próprio: superamplitude/sutiescrepusculos. Site: https://suitescrepusculo.com.br.
- Toda administração da VPS usa exclusivamente superamplitude/Gerente-VPS, .github/workflows/bridge-gerente-vps.yml, requests/vps-action.txt, runner gerente-vps. Ler AGENTS.md e docs/CANAL-OPERACIONAL-UNICO.md desse repositório.
- Runtime PHP: /home/sasuites/htdocs/suitescrepusculo.com.br. Baseline atual: deploy/runtime-manifest-20261106.json. Não reaplicar o monolito nem scripts de baseline antigo.
- O proprietário confirmou voz funcionando por chamada WhatsApp em 06/11/2026 e pediu explicitamente para não mexer nela. Preservar código/configuração e não reiniciar suites-crepusculo-attendance.service nem o gateway de chamadas em 18180 durante trabalho nas mensagens.
- Voz existente: /opt/suites-crepusculo-attendance, serviço suites-crepusculo-attendance.service, sessão crepusculo_company_1 no gateway existente 18180. Três bibliotecas foram copiadas do CRM com hashes verificados. Não criar outro gateway.
- Texto/áudios são independentes: /opt/suites-crepusculo-messages, serviço suites-crepusculo-messages.service, socket /run/suites-messages/messages.sock restrito ao grupo suites-atendimento. Reutiliza o gateway de mensagens CRM existente em 18190, serviço sa-crm-whatsapp-gateway.service, somente sessão suites-crepusculo.
- O adaptador do gateway desvia exclusivamente a sessão suites-crepusculo. As demais sessões continuam no fluxo original. Não alterar /opt/superamplitude/crm nem recriar o runtime legado 3101.
- Mensagens usam o banco do Suítes, conversations/messages e duas tabelas novas de vínculo/deduplicação. Respostas do painel seguem pelo gateway; Humano pausa IA e Híbrido/IA liberam a atribuição. Configuração/IA/base/voz existentes são reutilizadas. O pareamento das mensagens é separado das chamadas.
- Removido o grupo CRM do menu; acesso Chat e voz mostra perguntas e respostas por texto no painel de gestão.
- Não fabricar sessões administrativas para testes. Não exportar arquivos internos completos do CRM aos logs. Não versionar credenciais ou conteúdo de conversas.
- Sem backups locais; conservar dados e sessões. Registrar execução real e testar ao terminar. Teste de contrato não comprova mensagem externa entregue: registrar separadamente o resultado real do proprietário.
