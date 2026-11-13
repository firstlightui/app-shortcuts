import Foundation

// Stand-ins for NativePHP host types, used only by bin/typecheck-ios. Signatures copied from
// nativephp/mobile 4.7 resources/xcode/NativePHP/{Bridge/BridgeRouter,AppDelegate,DeepLinkRouter,DebugLogger}.swift.
// Keep them in sync when upgrading NativePHP; a real device build is the final check.

protocol BridgeFunction {
    func execute(parameters: [String: Any]) throws -> [String: Any]
}

struct BridgeResponse {
    static func success(data: [String: Any] = [:]) -> [String: Any] {
        data
    }

    static func error(code: String, message: String, data: [String: Any] = [:]) -> [String: Any] {
        ["status": "error", "code": code, "message": message, "data": data]
    }
}

extension Notification.Name {
    static let didReceiveShortcutItem = Notification.Name("NativePHP.didReceiveShortcutItem")
}

final class DeepLinkRouter {
    static let shared = DeepLinkRouter()

    func handle(url: URL) {}
}

final class DebugLogger {
    static let shared = DebugLogger()

    func log(_ message: String) {}
}
