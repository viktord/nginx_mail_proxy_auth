# nginx_mail_proxy_auth
Sample php code for auth_http in nginx mail proxy

You should add your users in a mysql database (sample table with "username" and "password" fields) and configure your mail host and mysql credentials in the code.
Then you make this code accessbile (put it in nginx/apache web server) and add it in the mandatory auth_http directive in your nginx mail proxy configuration

```
mail {
    server_name mail.example.com;
    auth_http   web-server/auth.php;

    proxy_pass_error_message on;
    ...
}
```
