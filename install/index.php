<?php
	function stylesheet_tag($filename, $id = false) {
		$timestamp = filemtime($filename);

		$id_part = $id ? "id=\"$id\"" : "";

		return "<link rel=\"stylesheet\" $id_part type=\"text/css\" href=\"$filename?$timestamp\"/>\n";
	}

	function javascript_tag($filename) {
		$query = "";

		if (!(strpos($filename, "?") === FALSE)) {
			$query = substr($filename, strpos($filename, "?")+1);
			$filename = substr($filename, 0, strpos($filename, "?"));
		}

		$timestamp = filemtime($filename);

		if ($query) $timestamp .= "&$query";

		return "<script type=\"text/javascript\" charset=\"utf-8\" src=\"$filename?$timestamp\"></script>\n";
	}
?>
<!DOCTYPE html>
<html>
<head>
	<title>feed.KIM - 安装程序</title>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
	<style type="text/css">
		textarea { font-size : 12px; }
	</style>
	<?php
		echo stylesheet_tag("../css/default.css");
		echo javascript_tag("../lib/prototype.js");
		echo javascript_tag("../lib/dojo/dojo.js");
		echo javascript_tag("../lib/dojo/tt-rss-layer.js");
	?>
</head>
<body class="flat ttrss_utility installer">

<script type="text/javascript">
	require(['dojo/parser', "dojo/ready", 'dijit/form/Button','dijit/form/CheckBox', 'dijit/form/Form',
		'dijit/form/Select','dijit/form/TextBox','dijit/form/ValidationTextBox'],function(parser, ready){
		ready(function() {
			parser.parse();
		});
	});
</script>

<?php

	// could be needed because of existing config.php
	function define_default($param, $value) {
		//
	}

	function make_password($length = 12) {
		$password = "";
		$possible = "0123456789abcdfghjkmnpqrstvwxyzABCDFGHJKMNPQRSTVWXYZ*%+^";

		$i = 0;

		while ($i < $length) {

			try {
				$idx = function_exists("random_int") ? random_int(0, strlen($possible) - 1) : mt_rand(0, strlen($possible) - 1);
			} catch (Exception $e) {
				$idx = mt_rand(0, strlen($possible) - 1);
			}

			$char = substr($possible, $idx, 1);

			if (!strstr($password, $char)) {
				$password .= $char;
				$i++;
			}
		}

		return $password;
	}


	function sanity_check($db_type) {
		$errors = array();

		if (version_compare(PHP_VERSION, '5.6.0', '<')) {
			array_push($errors, "需要5.6.0或更高版本的PHP，您用的版本是 " . PHP_VERSION . ".");
		}

		if (!function_exists("curl_init") && !ini_get("allow_url_fopen")) {
			array_push($errors, "php配置选项allow_url_fopen被禁用，curl函数不存在。启用allow_url_fopen或安装curl的php扩展。");
		}

		if (!function_exists("json_encode")) {
			array_push($errors, "需要对JSON的PHP支持，但未找到。");
		}

		if (!class_exists("PDO")) {
			array_push($errors, "需要PHP支持PDO，但未找到。");
		}

		if (!function_exists("mb_strlen")) {
			array_push($errors, "需要对mbstring函数的PHP支持，但未找到。");
		}

		if (!function_exists("hash")) {
			array_push($errors, "需要对hash()函数的php支持，但未找到。");
		}

		if (!function_exists("iconv")) {
			array_push($errors, "处理多个字符集需要对iconv的PHP支持。");
		}

		if (ini_get("safe_mode")) {
			array_push($errors, "PHP安全模式设置已过时，feedKIM不支持。");
		}

		if (!class_exists("DOMDocument")) {
			array_push($errors, "需要对domdocument的PHP支持，但未找到。");
		}

		return $errors;
	}

	function print_error($msg) {
		print "<div class='alert alert-error'>$msg</div>";
	}

	function print_notice($msg) {
		print "<div class=\"alert alert-info\">$msg</div>";
	}

	function pdo_connect($host, $user, $pass, $db, $type, $port = false) {

		$db_port = $port ? ';port=' . $port : '';
		$db_host = $host ? ';host=' . $host : '';

		try {
			$pdo = new PDO($type . ':dbname=' . $db . $db_host . $db_port,
				$user,
				$pass);

			return $pdo;
		} catch (Exception $e) {
		    print "<div class='alert alert-danger'>" . $e->getMessage() . "</div>";
		    return null;
        }
	}

	function make_config($DB_TYPE, $DB_HOST, $DB_USER, $DB_NAME, $DB_PASS,
			$DB_PORT, $SELF_URL_PATH) {

		$data = explode("\n", file_get_contents("../config.php-dist"));

		$rv = "";

		$finished = false;

		foreach ($data as $line) {
			if (preg_match("/define\('DB_TYPE'/", $line)) {
				$rv .= "\tdefine('DB_TYPE', '$DB_TYPE');\n";
			} else if (preg_match("/define\('DB_HOST'/", $line)) {
				$rv .= "\tdefine('DB_HOST', '$DB_HOST');\n";
			} else if (preg_match("/define\('DB_USER'/", $line)) {
				$rv .= "\tdefine('DB_USER', '$DB_USER');\n";
			} else if (preg_match("/define\('DB_NAME'/", $line)) {
				$rv .= "\tdefine('DB_NAME', '$DB_NAME');\n";
			} else if (preg_match("/define\('DB_PASS'/", $line)) {
				$rv .= "\tdefine('DB_PASS', '$DB_PASS');\n";
			} else if (preg_match("/define\('DB_PORT'/", $line)) {
				$rv .= "\tdefine('DB_PORT', '$DB_PORT');\n";
			} else if (preg_match("/define\('SELF_URL_PATH'/", $line)) {
				$rv .= "\tdefine('SELF_URL_PATH', '$SELF_URL_PATH');\n";
			} else if (!$finished) {
				$rv .= "$line\n";
			}

			if (preg_match("/\?\>/", $line)) {
				$finished = true;
			}
		}

		return $rv;
	}

	function is_server_https() {
		return (!empty($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] != 'off')) || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https');
	}

	function make_self_url_path() {
		$url_path = (is_server_https() ? 'https://' :  'http://') . $_SERVER["HTTP_HOST"] . parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);

		return $url_path;
	}

?>

<h1>feed.Keep In Myself 安装程序</h1>

<div class='content'>

<?php

	if (file_exists("../config.php")) {
		require "../config.php";

		if (!defined('_INSTALLER_IGNORE_CONFIG_CHECK')) {
			print_error("Error: config.php 已经存在，请删除后再安装程序");

			print "<form method='GET' action='../index.php'>
				<button type='submit' dojoType='dijit.form.Button' class='alt-primary'>返回feed.KIM</button>
				</form>";
			exit;
		}
	}

	@$op = $_REQUEST['op'];

	@$DB_HOST = strip_tags($_POST['DB_HOST']);
	@$DB_TYPE = strip_tags($_POST['DB_TYPE']);
	@$DB_USER = strip_tags($_POST['DB_USER']);
	@$DB_NAME = strip_tags($_POST['DB_NAME']);
	@$DB_PASS = strip_tags($_POST['DB_PASS']);
	@$DB_PORT = strip_tags($_POST['DB_PORT']);
	@$SELF_URL_PATH = strip_tags($_POST['SELF_URL_PATH']);

	if (!$SELF_URL_PATH) {
		$SELF_URL_PATH = preg_replace("/\/install\/$/", "/", make_self_url_path());
	}
?>

<form action="" method="post">
	<input type="hidden" name="op" value="testconfig">

	<h2>数据库参数设置</h2>

	<?php
		$issel_pgsql = $DB_TYPE == "pgsql" ? "selected='selected'" : "";
		$issel_mysql = $DB_TYPE == "mysql" ? "selected='selected'" : "";
	?>

	<fieldset>
		<label>数据库类型:</label>
		<select name="DB_TYPE" dojoType="dijit.form.Select">
			<option <?php echo $issel_mysql ?> value="mysql">MySQL</option>
			<option <?php echo $issel_pgsql ?> value="pgsql">PostgreSQL</option>
		</select>
	</fieldset>

	<fieldset>
		<label>登录名字:</label>
		<input dojoType="dijit.form.TextBox" required name="DB_USER" size="20" value="<?php echo $DB_USER ?>"/>
	</fieldset>

	<fieldset>
		<label>登录密码:</label>
		<input dojoType="dijit.form.TextBox" name="DB_PASS" size="20" type="password" value="<?php echo $DB_PASS ?>"/>
	</fieldset>

	<fieldset>
		<label>数据库名称:</label>
		<input dojoType="dijit.form.TextBox" required name="DB_NAME" size="20" value="<?php echo $DB_NAME ?>"/>
	</fieldset>

	<fieldset>
		<label>主机名:</label>
		<input dojoType="dijit.form.TextBox" name="DB_HOST" size="20" value="<?php echo $DB_HOST ?>"/>
		<span class="hint">如果需要可以设置</span>
	</fieldset>

	<fieldset>
		<label>端口:</label>
		<input dojoType="dijit.form.TextBox" name="DB_PORT" type="number" size="20" value="<?php echo $DB_PORT ?>"/>
		<span class="hint">Usually 3306 for MySQL or 5432 for PostgreSQL</span>
	</fieldset>

	<h2>其他设置</h2>

	<p>设置软件具体所在路径</p>

	<fieldset>
		<label>feed.KIM URL:</label>
		<input dojoType="dijit.form.TextBox" type="url" name="SELF_URL_PATH" placeholder="<?php echo $SELF_URL_PATH; ?>" value="<?php echo $SELF_URL_PATH ?>"/>
	</fieldset>

	<p><button type="submit" dojoType="dijit.form.Button" class="alt-primary">测试配置</button></p>
</form>

<?php if ($op == 'testconfig') { ?>

	<h2>正在检查配置</h2>

	<?php
		$errors = sanity_check($DB_TYPE);

		if (count($errors) > 0) {
			print "<p>某些配置测试失败，请在继续之前更正它们。</p>";

			print "<ul>";

			foreach ($errors as $error) {
				print "<li style='color : red'>$error</li>";
			}

			print "</ul>";

			exit;
		}

		$notices = array();

		if (!function_exists("curl_init")) {
			array_push($notices, "强烈建议在PHP中启用对curl的支持。");
		}

		if (function_exists("curl_init") && ini_get("open_basedir")) {
			array_push($notices, "url和open-basedir组合中断了对HTTP重定向的支持。有关更多信息，请参阅常见问题解答。");
		}

		if (!function_exists("idn_to_ascii")) {
			array_push($notices, "处理国际化域名需要对国际化函数的PHP支持。");
		}

        if ($DB_TYPE == "mysql" && !function_exists("mysqli_connect")) {
            array_push($notices, "PHP extension for MySQL (mysqli) is missing. This may prevent legacy plugins from working.");
        }

        if ($DB_TYPE == "pgsql" && !function_exists("pg_connect")) {
			array_push($notices, "缺少mysql（mysqli）的php扩展，这可能会阻止旧插件工作。 ");
        }

		if (count($notices) > 0) {
			print_notice("配置检查成功，但有一些小问题：");

			print "<ul>";

			foreach ($notices as $notice) {
				print "<li>$notice</li>";
			}

			print "</ul>";
		} else {
			print_notice("配置检查成功");
		}

	?>

	<h2>正在检查数据库</h2>

	<?php
		$pdo = pdo_connect($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME, $DB_TYPE, $DB_PORT);

		if (!$pdo) {
			print_error("无法使用指定的参数（driver: $DB_TYPE）连接到数据库。");
			exit;
		}

		print_notice("数据库测试成功");
	?>

	<h2>初始化数据库</h2>

	<p>在开始使用feed.KIM之前，需要初始化数据库。现在单击下面的按钮。</p>

	<?php
		$res = $pdo->query("SELECT true FROM ttrss_feeds");

		if ($res && $res->fetch()) {
			print_error("此数据库中已存在某些feed.KIM数据。如果继续数据库初始化，则当前数据<b>将会丢失</b>.");
			$need_confirm = true;
		} else {
			$need_confirm = false;
		}
	?>

	<table><tr><td>
	<form method="post">
		<input type="hidden" name="op" value="installschema">

		<input type="hidden" name="DB_USER" value="<?php echo $DB_USER ?>"/>
		<input type="hidden" name="DB_PASS" value="<?php echo $DB_PASS ?>"/>
		<input type="hidden" name="DB_NAME" value="<?php echo $DB_NAME ?>"/>
		<input type="hidden" name="DB_HOST" value="<?php echo $DB_HOST ?>"/>
		<input type="hidden" name="DB_PORT" value="<?php echo $DB_PORT ?>"/>
		<input type="hidden" name="DB_TYPE" value="<?php echo $DB_TYPE ?>"/>
		<input type="hidden" name="SELF_URL_PATH" value="<?php echo $SELF_URL_PATH ?>"/>

		<p>
		<?php if ($need_confirm) { ?>
			<button onclick="return confirm('Please read the warning above. Continue?')" type="submit"
					   class="alt-danger" dojoType="dijit.form.Button">初始化数据库</button>
		<?php } else { ?>
			<button type="submit" class="alt-danger" dojoType="dijit.form.Button">初始化数据库</button>
		<?php } ?>
		</p>
	</form>

	</td><td>
	<form method="post">
		<input type="hidden" name="DB_USER" value="<?php echo $DB_USER ?>"/>
		<input type="hidden" name="DB_PASS" value="<?php echo $DB_PASS ?>"/>
		<input type="hidden" name="DB_NAME" value="<?php echo $DB_NAME ?>"/>
		<input type="hidden" name="DB_HOST" value="<?php echo $DB_HOST ?>"/>
		<input type="hidden" name="DB_PORT" value="<?php echo $DB_PORT ?>"/>
		<input type="hidden" name="DB_TYPE" value="<?php echo $DB_TYPE ?>"/>
		<input type="hidden" name="SELF_URL_PATH" value="<?php echo $SELF_URL_PATH ?>"/>

		<input type="hidden" name="op" value="skipschema">

		<p><button type="submit" dojoType="dijit.form.Button">跳过初始化</button></p>
	</form>

	</td></tr></table>

	<?php

		} else if ($op == 'installschema' || $op == 'skipschema') {

			$pdo = pdo_connect($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME, $DB_TYPE, $DB_PORT);

			if (!$pdo) {
				print_error("Unable to connect to database using specified parameters.");
				exit;
			}

			if ($op == 'installschema') {

				print "<h2>正在初始化数据库… </h2>";

				$lines = explode(";", preg_replace("/[\r\n]/", "",
                    file_get_contents("../schema/ttrss_schema_".basename($DB_TYPE).".sql")));

				foreach ($lines as $line) {
					if (strpos($line, "--") !== 0 && $line) {
						$res = $pdo->query($line);

						if (!$res) {
							print_notice("Query: $line");
							print_error("Error: " . implode(", ", $this->pdo->errorInfo()));
                        }
					}
				}

				print_notice("数据库初始化完成");

			} else {
				print_notice("已跳过数据库初始化");
			}

			print "<h2>生成的配置文件</h2>";

			print "<p>复制以下文本，并另存为主目录中的<code>config.php<code>如果需要更改任何选项的默认值，建议将文件读懂。</p>";

			print "<p>复制文件后，您将能够使用默认用户名和密码组合登录：<code>admin<code>和<code>password<code>。别忘了马上更改密码！ </p>"; ?>

			<form action="" method="post">
				<input type="hidden" name="op" value="saveconfig">
				<input type="hidden" name="DB_USER" value="<?php echo $DB_USER ?>"/>
				<input type="hidden" name="DB_PASS" value="<?php echo $DB_PASS ?>"/>
				<input type="hidden" name="DB_NAME" value="<?php echo $DB_NAME ?>"/>
				<input type="hidden" name="DB_HOST" value="<?php echo $DB_HOST ?>"/>
				<input type="hidden" name="DB_PORT" value="<?php echo $DB_PORT ?>"/>
				<input type="hidden" name="DB_TYPE" value="<?php echo $DB_TYPE ?>"/>
				<input type="hidden" name="SELF_URL_PATH" value="<?php echo $SELF_URL_PATH ?>"/>
			<?php print "<textarea rows='20' style='width : 100%'>";
			echo make_config($DB_TYPE, $DB_HOST, $DB_USER, $DB_NAME, $DB_PASS,
				$DB_PORT, $SELF_URL_PATH);
			print "</textarea>"; ?>

			<hr/>

			<?php if (is_writable("..")) { ?>
				<p>我们现在也可以尝试自动保存文件</p>

				<p><button type="submit" dojoType='dijit.form.Button' class='alt-primary'>保存配置</button></p>
				</form>
			<?php } else {
				print_error("很遗憾，父目录不可写，因此我们无法自动保存config.php");
			}

		   print_notice("您可以通过更改上面的表单再次生成文件");

		} else if ($op == "saveconfig") {

			print "<h2>正在将配置文件保存到父目录… </h2>";

			if (!file_exists("../config.php")) {

				$fp = fopen("../config.php", "w");

				if ($fp) {
					$written = fwrite($fp, make_config($DB_TYPE, $DB_HOST,
						$DB_USER, $DB_NAME, $DB_PASS,
						$DB_PORT, $SELF_URL_PATH));

					if ($written > 0) {
						print_notice("成功保存config.php,您可以尝试现在加载feed.KIM</a>.");

					} else {
						print_notice("无法写入目录中的config.php");
					}

					fclose($fp);
				} else {
					print_error("无法在目录中打开config.php进行写入");
				}
			} else {
				print_error("config.php已经存在于目录中，拒绝覆盖");
			}
		}
	?>

</div>

</body>
</html>
