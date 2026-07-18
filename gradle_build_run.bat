@echo off
set JAVA_HOME=C:\jdk_extract\jdk17
set PATH=C:\jdk_extract\jdk17\bin;%PATH%
set ANDROID_HOME=%LOCALAPPDATA%\Android\Sdk
set ANDROID_SDK_ROOT=%LOCALAPPDATA%\Android\Sdk
cd /d "D:\Mobile App\MGS\nativephp\android"
call gradlew.bat assembleRelease -PMYAPP_UPLOAD_STORE_FILE="D:\Mobile App\MGS\credentials\app-release-key.jks" -PMYAPP_UPLOAD_KEY_ALIAS=app-key -PMYAPP_UPLOAD_STORE_PASSWORD=jailany -PMYAPP_UPLOAD_KEY_PASSWORD=jailany >> "D:\Mobile App\MGS\gradle_build.log" 2>&1
echo GRADLE_DONE_%ERRORLEVEL% >> "D:\Mobile App\MGS\gradle_build.log"
if exist "D:\Mobile App\MGS\nativephp\android\app\build\outputs\apk\release\app-release.apk" (
  copy /Y "D:\Mobile App\MGS\nativephp\android\app\build\outputs\apk\release\app-release.apk" "D:\Mobile App\MGS\MGS-release-signed.apk" >> "D:\Mobile App\MGS\gradle_build.log" 2>&1
  echo COPIED_APK >> "D:\Mobile App\MGS\gradle_build.log"
)
