<?php /** Vars: $h (id,name,kind,cfg), $csrf */ $c = $h['cfg'] ?? []; $secretSet = fn($k) => !empty($c[$k]) ? ' placeholder="•••••• (guardado; escriba para reemplazar)"' : ''; ?>
<form method="post" action="/admin/hostings"><?= $csrf ?><input type="hidden" name="accion" value="guardar"><input type="hidden" name="id" value="<?= (int) $h['id'] ?>">
<div class="row"><div><label>Nombre</label><input name="name" value="<?= e($h['name']) ?>" required maxlength="100"></div>
<div><label>Tipo</label><select name="kind"><option value="cpanel"<?= $h['kind'] === 'cpanel' ? ' selected' : '' ?>>cPanel (este hosting)</option><option value="agent"<?= $h['kind'] === 'agent' ? ' selected' : '' ?>>Agente (segundo hosting)</option></select></div>
<div><label>Dominio base de las webs</label><input name="domain_root" value="<?= e($c['domain_root'] ?? '') ?>" placeholder="servicom.gt"></div>
<div><label>Ruta de las webs (absoluta)</label><input name="webs_path" value="<?= e($c['webs_path'] ?? '') ?>" placeholder="/home/USUARIO/webs-clientes"></div></div>
<p class="mut" style="margin:.8rem 0 0"><b>cPanel:</b></p>
<div class="row"><div><label>Servidor cPanel</label><input name="host" value="<?= e($c['host'] ?? 'localhost') ?>"></div><div><label>Puerto</label><input name="port" type="number" value="<?= (int) ($c['port'] ?? 2083) ?>"></div>
<div><label>Usuario de cPanel</label><input name="user" value="<?= e($c['user'] ?? '') ?>" autocomplete="off"></div><div><label>Token de API</label><input name="token" type="password" autocomplete="new-password"<?= $secretSet('token') ?>></div>
<div><label>Carpeta personal (home)</label><input name="home" value="<?= e($c['home'] ?? '') ?>" placeholder="/home/USUARIO"></div></div>
<p class="mut" style="margin:.8rem 0 0"><b>Agente:</b></p>
<div class="row"><div><label>URL de agent.php</label><input name="agent_url" value="<?= e($c['agent_url'] ?? '') ?>" placeholder="https://hosting2.ejemplo.com/agent.php"></div><div><label>Secreto compartido (≥ 32 caracteres)</label><input name="agent_secret" type="password" autocomplete="new-password"<?= $secretSet('agent_secret') ?>></div></div>
<label><input type="checkbox" name="verify_ssl" value="1"<?= !isset($c['verify_ssl']) || $c['verify_ssl'] ? ' checked' : '' ?>> Verificar certificado SSL</label>
<p><button class="btn">Guardar</button></p></form>
