# Suítes Crepúsculo

Site: https://suitescrepusculo.com.br/

Repositório próprio do código e dos recursos do projeto. A administração da VPS continua exclusivamente pelo runner existente `gerente-vps`, no repositório `superamplitude/Gerente-VPS`. Os scripts deste projeto são executados por referências imutáveis e SHA-256 conferido; não há outro runner.

## Publicado

- Entrada PHP enxuta, sem CSS antes do protocolo PHP; 53 módulos separados em `pages/`, preservando endereços, bootstrap, autenticação e permissões. Alguns conteúdos relacionados continuam agrupados no mesmo módulo.
- Mapa: cadastro de reserva, nova unidade, acomodações, lista de reservas e configuração da automação abrem em janelas sobrepostas. Botões e campos compactos, com verificação em desktop e celular.
- Painel: voz natural pela Central de Comunicação existente, com perfil Callirrhoe / gemini-3.8-live herdado do CRM, saudação, pergunta por texto, microfone quando suportado pelo navegador, repetição e interrupção. A reprodução automática está sujeita à política de áudio do navegador.
- Respostas usam a IA e a base de conhecimento já existentes na pousada.
- Adaptador WhatsApp conectado ao gateway existente em `http://127.0.0.1:18180`, substituindo o endereço antigo 18080. Credenciais ficam fora da raiz pública.
- Código PHP, JS e CSS atuais versionados aqui, junto a 36 imagens originais transferidas com os mesmos hashes Git. Os 49 arquivos específicos do Crepúsculo foram retirados da branch atual do Gerente.

## Evidências

| Verificação | Resultado |
| --- | --- |
| Separação de páginas | [Execução 42100299414](https://github.com/superamplitude/Gerente-VPS/actions/runs/42100299414), sintaxe PHP e HTTP aprovados |
| Janelas do mapa | [Execução 42101988824](https://github.com/superamplitude/Gerente-VPS/actions/runs/42101988824), cinco ações em sobreposição, celular e zero erros JS |
| Voz e IA | [Execução 42104152952](https://github.com/superamplitude/Gerente-VPS/actions/runs/42104152952), WAV real de 282596 bytes, reprodução iniciada e resposta real da IA |
| JSON do mapa | [Execução 42108023447](https://github.com/superamplitude/Gerente-VPS/actions/runs/42108023447), resposta limpa, leitura de reserva real sem gravações e layout preservado |
| Autenticação da voz | Sem sessão: HTTP401; CSRF inválido: HTTP419 |
| Gateway WhatsApp | [Execução 42104889724](https://github.com/superamplitude/Gerente-VPS/actions/runs/42104889724), acesso confirmado como usuário Linux do site |
| QR Code real | [Execução 42105943746](https://github.com/superamplitude/Gerente-VPS/actions/runs/42105943746), payload gerado, sem mensagens ou ligações externas; canvas real de 320×320 também conferido no navegador |

O manifesto em `deploy/runtime-manifest-20261105.json` registra os hashes do código publicado. Configuração de produção, chaves, banco e dados privados não estão no repositório.

## Atualização de 06/11/2026

- Grupo CRM removido do menu.
- Botão **Chat e voz** no painel de gestão, com perguntas e respostas por texto; perguntas não são mais ignoradas enquanto a saudação prepara áudio. Enviar por Enter, interrupção/cancelamento de áudio e histórico visual verificados. A IA real retornou HTTP 200.
- Chamadas WhatsApp: três módulos do CRM copiados por hash para o adaptador do Suítes. O proprietário confirmou uma ligação com voz funcionando e pediu para preservar esse atendimento. Nenhuma mudança ou reinício da voz foi realizado durante a publicação de mensagens.
- Texto e áudios: publicados usando a sessão `suites-crepusculo` no gateway de mensagens existente em 18190. Processador próprio por socket Unix restrito, sem novo gateway nem endpoint público. Conversas aparecem no Atendimento; respostas humanas seguem ao WhatsApp; deduplicação, modos Humano/Híbrido/IA e codificação Ogg/Opus testados sem envios externos.
- Mensagens precisam de pareamento próprio pelo botão **Gerar QR de mensagens**, separado do QR de chamadas. Publicação concluída pelo [pedido f3676f6](https://github.com/superamplitude/Gerente-VPS/commit/f3676f62c9337b8667501db20abddbe9852e6bcb), com `exit_code=0` em 06/11/2026 às 10:51:10 UTC.
- Baseline atual: `deploy/runtime-manifest-20261106.json`. O manifesto anterior é histórico.

## Pendente de homologação externa

A voz em chamada foi confirmada pelo proprietário e permanece congelada por pedido dele. O primeiro pareamento de mensagens chegou a abrir e foi revogado com 401. A correção isolada para o Suítes usa Baileys 6.7.23, mantendo a 7.0.0-rc14 das demais sessões; implantação aprovada na [execução 42208537603](https://github.com/superamplitude/Gerente-VPS/actions/runs/42208537603), sem mudança ou reinício da voz. Um novo QR foi gerado.

O teste real de processamento no banco e na IA produziu resposta de 187 caracteres, ignorou duplicidade e respeitou o modo Humano. Todos os registros foram desfeitos em transação. A homologação de texto/áudio externo continua dependendo de parear o QR novo e confirmar entrega/resposta em outro aparelho; serviço ativo e teste interno não comprovam essa entrega.

O arquivo `integrations/attendance/server.mjs` é um protótipo anterior não instalado. Os adaptadores ativos são `calls-worker.mjs` e `messages-worker.mjs`, além dos controladores PHP do painel e da Central de Comunicação existente.

Nenhum backup local foi criado, nenhum backup original foi utilizado ou alterado, e os runtimes CRM/AEL não foram reiniciados.
