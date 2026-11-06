# Suítes Crepúsculo — regras de implementação
- A administração e a publicação são feitas somente pelo canal existente no repositório privado superamplitude/Gerente-VPS. Leia primeiro as regras operacionais desse repositório.
- Cada recurso tem um arquivo pages/<rota>.php registrado em config/page-routes.json. index.php apenas inicializa e despacha. Não restaurar versões antigas agrupadas.
- O perfil de fala fica independente das telas, em VoiceProfileService e na tabela suites_voice_profiles. Alterações de página/layout não podem substituir fontes de voz nem escrever no perfil.
- Respeitar deploy/module-boundaries.json. Mudanças de voz exigem atualizar explicitamente deploy/voice-revision.json e validar áudio real antes de concluir a publicação.
- Preservar a identidade vocal, credenciais, pareamentos, permissões e dados existentes. Não alterar outros projetos.
- Não fabricar sessões administrativas em produção. Não exportar fontes privadas, credenciais ou conversas para logs.
- Registrar execução real no repositório operacional e testar ao terminar. Geração técnica de áudio não comprova avaliação auditiva humana.

- Todas as rotas administrativas e o login estão fisicamente em painel/pages/<rota>.php; o portal público permanece em pages/<rota>.php. Entrada de login dedicada: /painel/ via o mesmo Auth/DB/CSRF. Sem segundo servidor, runner ou credenciais.
