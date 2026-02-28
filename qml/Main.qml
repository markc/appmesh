import QtQuick
import QtQuick.Controls
import QtQuick.Layouts
import AppMesh

ApplicationWindow {
    id: root
    width: 520
    height: 700
    visible: true
    title: "AppMesh Test"

    function exec(port, cmd, args) {
        let result = AppMeshBridge.portExecute(port, cmd, args || {})
        output.text = JSON.stringify(result, null, 2)
    }

    Connections {
        target: AppMeshBridge
        function onMeshMessage(channel, data) {
            msgLog.append(Qt.formatTime(new Date(), "HH:mm:ss") + " [" + channel + "] " + data)
        }
    }

    ScrollView {
        anchors.fill: parent
        anchors.margins: 16

        ColumnLayout {
            width: parent.width
            spacing: 16

            // Status
            Label {
                text: AppMeshBridge.available
                    ? "Library loaded — ports: " + AppMeshBridge.ports.join(", ")
                    : "Library NOT loaded"
                color: AppMeshBridge.available ? "green" : "red"
                font.bold: true
                Layout.fillWidth: true
                wrapMode: Text.Wrap
            }

            // Messaging
            GroupBox {
                title: "Mesh Messaging"
                Layout.fillWidth: true
                ColumnLayout {
                    anchors.fill: parent
                    TextField { id: msgText; placeholderText: "Message"; text: "Hello mesh!"; Layout.fillWidth: true }
                    RowLayout {
                        Button {
                            text: "Broadcast"
                            onClicked: AppMeshBridge.sendMessage("chat", msgText.text)
                        }
                        Button {
                            text: "Notify + Broadcast"
                            onClicked: {
                                root.exec("notify", "send", { summary: "AppMesh", body: msgText.text })
                                AppMeshBridge.sendMessage("chat", msgText.text)
                            }
                        }
                    }
                    TextArea {
                        id: msgLog
                        readOnly: true
                        wrapMode: TextArea.Wrap
                        Layout.fillWidth: true
                        Layout.minimumHeight: 80
                        font.family: "monospace"
                        font.pointSize: 9
                        placeholderText: "Messages will appear here..."
                    }
                }
            }

            // Notify
            GroupBox {
                title: "Notify"
                Layout.fillWidth: true
                ColumnLayout {
                    anchors.fill: parent
                    TextField { id: notifyTitle; placeholderText: "Title"; text: "AppMesh"; Layout.fillWidth: true }
                    TextField { id: notifyBody; placeholderText: "Body"; text: "Hello from QML!"; Layout.fillWidth: true }
                    Button {
                        text: "Send Notification"
                        onClicked: root.exec("notify", "send", {
                            summary: notifyTitle.text,
                            body: notifyBody.text
                        })
                    }
                }
            }

            // Clipboard
            GroupBox {
                title: "Clipboard"
                Layout.fillWidth: true
                ColumnLayout {
                    anchors.fill: parent
                    TextField { id: clipText; placeholderText: "Text to copy"; Layout.fillWidth: true }
                    RowLayout {
                        Button { text: "Get"; onClicked: root.exec("clipboard", "get") }
                        Button { text: "Set"; onClicked: root.exec("clipboard", "set", { text: clipText.text }) }
                    }
                }
            }

            // Windows
            GroupBox {
                title: "Windows"
                Layout.fillWidth: true
                ColumnLayout {
                    anchors.fill: parent
                    Button { text: "List Windows"; onClicked: root.exec("windows", "list") }
                }
            }

            // Screenshot
            GroupBox {
                title: "Screenshot"
                Layout.fillWidth: true
                ColumnLayout {
                    anchors.fill: parent
                    Button { text: "Take Screenshot"; onClicked: root.exec("screenshot", "take") }
                }
            }

            // Output
            GroupBox {
                title: "Result"
                Layout.fillWidth: true
                TextArea {
                    id: output
                    readOnly: true
                    wrapMode: TextArea.Wrap
                    Layout.fillWidth: true
                    Layout.minimumHeight: 150
                    font.family: "monospace"
                    text: "Click a button to test..."
                }
            }
        }
    }
}
