
#Business Card Wallet API

1. Gets request from the app with text. (`index.php`)
2. Connects to expert.ai get the data
3. If token expired acquire new token and then connect to expert.ai
4. Process the data. (`process.php`)
5. Send the response to app

To run:
```shell
composer install
```

Copy the `.env.example` to `.env` and fill the expert.ai username and password

```shell
php -S 0.0.0.0:7077
```
