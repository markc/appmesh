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

    AppMeshBridge { id: mesh }

    function exec(port, cmd, args) {
        let result = mesh.portExecute(port, cmd, args || {})
        output.text = JSON.stringify(result, null, 2)
    }

    ScrollView {
        anchors.fill: parent
        anchors.margins: 16

        ColumnLayout {
            width: parent.width
            spacing: 16

            // Status
            Label {
                text: mesh.available
                    ? "Library loaded — ports: " + mesh.ports.join(", ")
                    : "Library NOT loaded"
                color: mesh.available ? "green" : "red"
                font.bold: true
                Layout.fillWidth: true
                wrapMode: Text.Wrap
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
