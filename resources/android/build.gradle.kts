// Off-device check for the plugin's Kotlin. NativePHP compiles src/ into the app itself;
// this build only typechecks every source against android.jar and stand-ins for the host types.
plugins {
    kotlin("jvm") version "2.4.21"
}

val androidSdk = System.getenv("ANDROID_HOME")
    ?: System.getenv("ANDROID_SDK_ROOT")
    ?: "${System.getProperty("user.home")}/Library/Android/sdk"

val androidJar = file("$androidSdk/platforms/android-36/android.jar")

val hostCheck by sourceSets.creating

kotlin {
    sourceSets {
        main {
            kotlin.setSrcDirs(emptyList<String>())
        }
        getByName("hostCheck") {
            kotlin.setSrcDirs(listOf("src", "host-stubs"))
        }
    }
}

dependencies {
    "hostCheckCompileOnly"(files(androidJar))
}
