// Stand-in for NativePHP's NativeElementBridge, used only by the hostCheck compile. Signature copied from
// nativephp/mobile 4.7 resources/androidstudio/.../ui/nativerender/NativeElementBridge.kt.
package com.nativephp.mobile.ui.nativerender

class NativeElementBridge private constructor() {
    companion object {
        fun postNativeEventToQueue(eventName: String, payloadJson: String): Boolean = false
    }
}
