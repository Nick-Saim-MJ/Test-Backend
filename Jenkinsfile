pipeline {
  agent any

  environment {
    SONARQUBE_SERVER = 'sonarqube-capachica'         // nombre exacto en Manage Jenkins → System
    SONAR_SCANNER    = 'SonarScanner-CLI'            // nombre exacto en Global Tool Configuration
    SONAR_TOKEN_CRED = 'sonar-token-capachica'       // ID de credencial Secret text con tu token Sonar
  }

  options { ansiColor('xterm'); timestamps() }

  stages {
    stage('Checkout') {
      steps {
        // Usa una credencial de GitHub GUARDADA (NO pegues el PAT en el Jenkinsfile)
        // Crea antes una credencial tipo "Username/Password" o "Secret text" y usa su ID.
        checkout([$class: 'GitSCM',
          branches: [[name: '*/main']],
          userRemoteConfigs: [[
            url: 'https://github.com/Nick-Saim-MJ/Test-Backend.git',
            credentialsId: 'github-token'   // <--- TU ID DE CREDENCIAL, no el PAT en claro
          ]]
        ])
      }
    }

    stage('Install deps (Composer)') {
      steps {
        dir('turismo-backend') {
          sh '''
            php -v || true
            composer --version || true

            composer install --no-interaction --prefer-dist  # con dev para phpunit
          '''
        }
      }
    }

    stage('Test + Coverage (PHPUnit)') {
      steps {
        dir('turismo-backend') {
          sh '''
            # Habilita cobertura (PCOV es rápido). Si ya tienes Xdebug, omite esto.
            (pecl install pcov || true)
            (php -m | grep -qi pcov || docker-php-ext-enable pcov || true)
            echo "pcov.enabled=1" > /usr/local/etc/php/conf.d/pcov.ini || true

            # Entorno de testing simple con SQLite en memoria
            export APP_ENV=testing
            export DB_CONNECTION=sqlite
            export DB_DATABASE=':memory:'
            export CACHE_STORE=array
            export QUEUE_CONNECTION=sync
            export SESSION_DRIVER=array
            export MAIL_MAILER=array

            php artisan key:generate
            php artisan config:clear
            php artisan migrate:fresh --seed --env=testing -n || true

            mkdir -p coverage
            ./vendor/bin/phpunit \
              --coverage-clover coverage/clover.xml \
              --log-junit coverage/junit.xml
            test -s coverage/clover.xml
          '''
        }
      }
      post {
        always {
          junit 'turismo-backend/coverage/junit.xml'
          // publishHTML(...) si generas coverage HTML
        }
      }
    }

    stage('SonarQube Analysis') {
      steps {
        dir('turismo-backend') {
          withSonarQubeEnv("${env.SONARQUBE_SERVER}") {
            withCredentials([string(credentialsId: env.SONAR_TOKEN_CRED, variable: 'SONAR_TOKEN')]) {
              tool name: "${env.SONAR_SCANNER}", type: 'hudson.plugins.sonar.SonarRunnerInstallation'
              sh 'sonar-scanner -Dsonar.login=$SONAR_TOKEN'
            }
          }
        }
      }
    }

    stage('Quality Gate') {
      steps {
        timeout(time: 10, unit: 'MINUTES') {
          waitForQualityGate abortPipeline: true
        }
      }
    }
  }
}