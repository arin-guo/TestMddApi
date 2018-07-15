项目部署需知
1：修改数据库配置
2：备份以前的图片，路径public-upload
3：给予runtime 读写权限。必要的情况public也要给予权限。
4：服务器必须开启rewrite_so模块，php必须开启openssl支持。


TP5.0开发随笔
1：分组无需配置文件，使用cmd进入项目根目录，使用命令php think build --module admin 可生成一个标准分组。
	注意：如果修改了index.php里的application为com,则think文件里也要对应修改。
	
2:如需要判断空模块，则修改app.php源码第2411行为：return redirect($request->domain());

3:在微信模块，如出现cURL error 60: SSL certificate problem错误，请配置CURL证书，
	方法：
		1.下载 CA 证书

		你可以从 http://curl.haxx.se/ca/cacert.pem 下载 或者 使用微信官方提供的证书中的 CA 证书 rootca.pem 也是同样的效果。

		2.在 php.ini 中配置 CA 证书

		只需要将上面下载好的 CA 证书放置到您的服务器上某个位置，然后修改 php.ini 的 curl.cainfo 为该路径（绝对路径！），重启 php-fpm 服务即可。

		curl.cainfo = D://curl/cacert.pem
		注意证书文件路径为绝对路径！以自己实际情况为准。

		其它修改 HTTP 类源文件的方式是不允许的。
		
		