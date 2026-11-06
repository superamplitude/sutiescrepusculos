<?php use App\Core\U; ?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow"><title>Acessar painel</title>
<style>
*{box-sizing:border-box}
body{margin:0;min-height:100dvh;display:grid;place-items:center;padding:20px;background:#edf2f1;font:14px Arial,sans-serif;color:#213b34}
.access{width:min(440px,100%);background:#fff;border:1px solid #cddbd5;border-radius:14px;box-shadow:0 14px 44px #172f2419;overflow:hidden}
.head{padding:24px 25px 17px;border-bottom:1px solid #e1ebe6}.head small{color:#58766b;font-weight:700}
.head h1{font-size:23px;margin:9px 0}.head p{font-size:13px;color:#60796f;line-height:1.5;margin:0}
.content{padding:21px 25px 25px}.content .page-head{display:none}.content form{display:grid;gap:15px}
.content label{display:grid;gap:7px;font-size:13px;font-weight:700}.content input{width:100%;min-height:43px;padding:10px 12px;font:15px Arial,sans-serif;border:1px solid #a9c1b7;border-radius:8px}
.content input:focus{outline:2px solid #e6791c;outline-offset:1px}
.content button{height:43px;border:0;border-radius:8px;background:#e87919;color:#fff;font-weight:700;cursor:pointer}
.content button:hover{background:#c96211}.alert{margin-bottom:13px;padding:10px;border:1px solid #e9b7b0;color:#853b2a;background:#fff5f3;border-radius:7px}
.footer{padding:13px 25px;border-top:1px solid #e1ebe6;display:flex;justify-content:space-between;gap:10px;font-size:12px;color:#6f8179}
.footer a{color:#176c5e;text-decoration:none}
</style></head><body><main class="access"><header class="head"><small>PAINEL ADMINISTRATIVO</small><h1>Acessar painel</h1><p>Entre com seu e-mail e senha para gerenciar o estabelecimento.</p></header>
<section class="content">
<?php if($m=U::flash('ok')):?><div class="alert"><?=U::e($m)?></div><?php endif;?>
<?php if($m=U::flash('err')):?><div class="alert" role="alert"><?=U::e($m)?></div><?php endif;?>
<?=$content??''?>
</section><footer class="footer"><a href="/">Voltar ao portal</a><span>Acesso restrito</span></footer></main></body></html>
