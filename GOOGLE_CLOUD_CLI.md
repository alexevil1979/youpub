# Установка Google Cloud CLI

## ⚠️ Важно

**Google Cloud CLI НЕ обязателен для работы YouTube OAuth интеграции!**

Для подключения YouTube достаточно:
1. Зайти в Google Cloud Console через браузер
2. Создать OAuth credentials
3. Скопировать Client ID и Secret в `config/env.php`

CLI нужен только если вы хотите управлять Google Cloud через командную строку.

## Установка на Windows (локальная машина)

### Вариант 1: Через установщик (рекомендуется)

1. Скачайте установщик:
   - Перейдите на https://cloud.google.com/sdk/docs/install
   - Скачайте установщик для Windows

2. Запустите установщик и следуйте инструкциям

3. После установки откройте PowerShell и выполните:
   ```powershell
   gcloud --version
   ```

### Вариант 2: Через Chocolatey

```powershell
choco install gcloudsdk
```

### Вариант 3: Через PowerShell (автоматическая установка)

```powershell
# Скачать и установить
(New-Object Net.WebClient).DownloadFile("https://dl.google.com/dl/cloudsdk/channels/rapid/GoogleCloudSDKInstaller.exe", "$env:Temp\GoogleCloudSDKInstaller.exe")
& $env:Temp\GoogleCloudSDKInstaller.exe
```

## Установка на Linux VPS

### Ubuntu/Debian

```bash
# Добавить репозиторий
echo "deb [signed-by=/usr/share/keyrings/cloud.google.gpg] https://packages.cloud.google.com/apt cloud-sdk main" | sudo tee -a /etc/apt/sources.list.d/google-cloud-sdk.list

# Импортировать ключ
curl https://packages.cloud.google.com/apt/doc/apt-key.gpg | sudo apt-key --keyring /usr/share/keyrings/cloud.google.gpg add -

# Установить
sudo apt-get update && sudo apt-get install google-cloud-cli
```

### CentOS/RHEL

```bash
# Добавить репозиторий
sudo tee -a /etc/yum.repos.d/google-cloud-sdk.repo << EOM
[google-cloud-cli]
name=Google Cloud CLI
baseurl=https://packages.cloud.google.com/yum/repos/cloud-sdk-el8-x86_64
enabled=1
gpgcheck=1
repo_gpgcheck=0
gpgkey=https://packages.cloud.google.com/yum/doc/yum-key.gpg
       https://packages.cloud.google.com/yum/doc/rpm-package-key.gpg
EOM

# Установить
sudo yum install google-cloud-cli
```

## Первоначальная настройка

После установки выполните:

```bash
gcloud init
```

Это запустит интерактивную настройку:
1. Выберите аккаунт Google
2. Выберите проект
3. Настройте регион (опционально)

## Авторизация

```bash
gcloud auth login
```

Откроется браузер для авторизации.

## Полезные команды

```bash
# Список проектов
gcloud projects list

# Выбрать проект
gcloud config set project PROJECT_ID

# Список API
gcloud services list

# Включить API
gcloud services enable youtube.googleapis.com

# Список OAuth clients
gcloud alpha iap oauth-clients list

# Создать OAuth client (через веб-интерфейс проще)
# Лучше использовать Google Cloud Console
```

## Для YouTube OAuth интеграции

**CLI не нужен!** Просто:

1. Откройте https://console.cloud.google.com/ в браузере
2. Создайте OAuth credentials через веб-интерфейс
3. Скопируйте Client ID и Secret

Это намного проще, чем через CLI.
