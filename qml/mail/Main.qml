import QtQuick
import QtQuick.Controls as QQC2
import QtQuick.Layouts
import org.kde.kirigami as Kirigami
import AppMesh

Kirigami.ApplicationWindow {
    id: root
    width: 1024
    height: 700
    visible: true
    title: "AppMesh Mail"

    property var mailboxes: []
    property var messages: []
    property string currentMailbox: ""
    property string currentMailboxName: "Inbox"
    property bool connected: false
    property bool replyAboveQuote: true  // true = top-post (Thunderbird default)

    // Format epoch seconds or ISO date string to human-readable
    function formatDate(d) {
        if (!d) return ""
        let date
        // If it's a pure number (epoch seconds), convert
        if (/^\d+$/.test(d)) {
            date = new Date(parseInt(d) * 1000)
        } else {
            date = new Date(d)
        }
        if (isNaN(date.getTime())) return d
        return date.toLocaleString(Qt.locale(), "ddd d MMM yyyy HH:mm")
    }

    // Quote text with > prefix for replies
    function quoteText(email) {
        let from = email.from || "someone"
        let date = formatDate(email.date)
        let body = email.body || email.preview || ""
        let header = "On " + date + ", " + from + " wrote:"
        let quoted = body.split("\n").map(function(line) { return "> " + line }).join("\n")
        return header + "\n" + quoted
    }

    // --- Connection & Data loading ---

    function connectAndLoad(url, user, pass) {
        let result = AppMeshBridge.portExecute("mail", "connect", {
            url: url, user: user, pass: pass
        })
        if (result.ok) {
            connected = true
            showPassiveNotification(result.ok)
            loadMailboxes()
            return true
        } else if (result.error) {
            showPassiveNotification("Connect failed: " + result.error.message)
            return false
        }
        return false
    }

    function checkStatus() {
        let result = AppMeshBridge.portExecute("mail", "status", {})
        if (result.ok && result.ok.indexOf("connected") === 0) {
            connected = true
            return true
        }
        return false
    }

    function loadMailboxes() {
        let result = AppMeshBridge.portExecute("mail", "mailboxes", {})
        if (result.ok) {
            mailboxes = result.ok
            // Auto-select Inbox
            for (let i = 0; i < mailboxes.length; i++) {
                if (mailboxes[i].name === "Inbox" || mailboxes[i].role === "Inbox") {
                    selectMailbox(mailboxes[i].id, mailboxes[i].name)
                    return
                }
            }
            // Fallback: select first mailbox
            if (mailboxes.length > 0) {
                selectMailbox(mailboxes[0].id, mailboxes[0].name)
            }
        } else if (result.error) {
            showPassiveNotification("Error: " + result.error.message)
        }
    }

    function selectMailbox(id, name) {
        currentMailbox = id
        currentMailboxName = name
        loadMessages()
    }

    function loadMessages() {
        if (!currentMailbox) return
        let result = AppMeshBridge.portExecute("mail", "query", {
            mailbox: currentMailbox,
            limit: "50"
        })
        if (result.ok) {
            messages = result.ok
        } else {
            messages = []
        }
    }

    function readMessage(id) {
        let result = AppMeshBridge.portExecute("mail", "read", { id: id })
        if (result.ok) {
            detailPage.email = result.ok
            pageStack.push(detailPage)
        }
    }

    function sendMail(to, subject, body) {
        let result = AppMeshBridge.portExecute("mail", "send", {
            to: to, subject: subject, body: body
        })
        if (result.ok) {
            showPassiveNotification(result.ok)
            composeSheet.close()
        } else if (result.error) {
            showPassiveNotification("Error: " + result.error.message)
        }
    }

    function replyToMessage(id, body) {
        let result = AppMeshBridge.portExecute("mail", "reply", {
            id: id, body: body
        })
        if (result.ok) {
            showPassiveNotification(result.ok)
            replySheet.close()
        } else if (result.error) {
            showPassiveNotification("Error: " + result.error.message)
        }
    }

    function deleteMessage(id) {
        let result = AppMeshBridge.portExecute("mail", "delete", { id: id })
        if (result.ok) {
            showPassiveNotification(result.ok)
            pageStack.pop()
            loadMessages()
        }
    }

    function flagMessage(id, flagged) {
        let cmd = flagged ? "unflag" : "flag"
        let result = AppMeshBridge.portExecute("mail", cmd, { id: id })
        if (result.ok) showPassiveNotification(result.ok)
    }

    function moveMessage(id, mailbox) {
        let result = AppMeshBridge.portExecute("mail", "move", {
            id: id, mailbox: mailbox
        })
        if (result.ok) {
            showPassiveNotification(result.ok)
            pageStack.pop()
            loadMessages()
        }
    }

    function searchMessages(text) {
        let result = AppMeshBridge.portExecute("mail", "search", { text: text })
        if (result.ok) {
            messages = result.ok
            currentMailboxName = "Search: " + text
        }
    }

    Component.onCompleted: {
        if (AppMeshBridge.available) {
            // Check if already connected (env vars auto-connect)
            if (checkStatus()) {
                loadMailboxes()
            } else {
                loginDialog.open()
            }
        } else {
            loginDialog.open()
        }
    }

    // --- Global Drawer (Mailbox sidebar) ---

    globalDrawer: Kirigami.GlobalDrawer {
        id: drawer
        title: "Mailboxes"
        titleIcon: "mail-folder-inbox"
        modal: false
        collapsible: true
        collapsed: false
        width: 220

        header: ColumnLayout {
            Layout.fillWidth: true
            spacing: Kirigami.Units.smallSpacing

            QQC2.TextField {
                id: searchField
                Layout.fillWidth: true
                Layout.margins: Kirigami.Units.smallSpacing
                placeholderText: "Search mail..."
                onAccepted: {
                    if (text.length > 0) searchMessages(text)
                }
            }
        }

        actions: [
            Kirigami.Action {
                text: root.connected ? "Log out" : "Log in"
                icon.name: root.connected ? "system-log-out" : "network-connect"
                onTriggered: {
                    if (root.connected) {
                        root.connected = false
                        root.mailboxes = []
                        root.messages = []
                        root.currentMailbox = ""
                        root.currentMailboxName = "Inbox"
                        loginDialog.open()
                    } else {
                        loginDialog.open()
                    }
                }
            },
            Kirigami.Action {
                text: "Compose"
                icon.name: "mail-message-new"
                enabled: root.connected
                onTriggered: composeSheet.open()
            },
            Kirigami.Action {
                text: "Refresh"
                icon.name: "view-refresh"
                enabled: root.connected
                onTriggered: {
                    loadMailboxes()
                    loadMessages()
                }
            }
        ]

        Repeater {
            model: root.mailboxes

            delegate: Kirigami.Action {
                required property var modelData
                text: {
                    let name = modelData.name || "Unknown"
                    let unread = modelData.unread || 0
                    return unread > 0 ? name + " (" + unread + ")" : name
                }
                icon.name: {
                    let role = (modelData.role || "").toLowerCase()
                    if (role.indexOf("inbox") >= 0) return "mail-folder-inbox"
                    if (role.indexOf("sent") >= 0) return "mail-folder-sent"
                    if (role.indexOf("draft") >= 0) return "mail-folder-drafts"
                    if (role.indexOf("trash") >= 0) return "user-trash"
                    if (role.indexOf("junk") >= 0 || role.indexOf("spam") >= 0) return "mail-mark-junk"
                    if (role.indexOf("archive") >= 0) return "mail-folder-inbox"
                    return "folder-mail"
                }
                onTriggered: root.selectMailbox(modelData.id, modelData.name)
            }
        }
    }

    // --- Message List (main page) ---

    pageStack.initialPage: Kirigami.ScrollablePage {
        id: listPage
        title: root.currentMailboxName

        actions: [
            Kirigami.Action {
                text: "Compose"
                icon.name: "mail-message-new"
                onTriggered: composeSheet.open()
            }
        ]

        ListView {
            id: messageList
            model: root.messages

            delegate: QQC2.ItemDelegate {
                required property var modelData
                required property int index
                width: ListView.view.width

                contentItem: ColumnLayout {
                    spacing: 2

                    RowLayout {
                        Layout.fillWidth: true
                        QQC2.Label {
                            text: modelData.from || "Unknown"
                            font.bold: true
                            elide: Text.ElideRight
                            Layout.fillWidth: true
                        }
                        QQC2.Label {
                            text: root.formatDate(modelData.date)
                            font.pointSize: Kirigami.Theme.smallFont.pointSize
                            opacity: 0.7
                        }
                    }

                    QQC2.Label {
                        text: modelData.subject || "(no subject)"
                        elide: Text.ElideRight
                        Layout.fillWidth: true
                    }

                    QQC2.Label {
                        text: modelData.preview || ""
                        elide: Text.ElideRight
                        Layout.fillWidth: true
                        opacity: 0.6
                        font.pointSize: Kirigami.Theme.smallFont.pointSize
                    }
                }

                onClicked: root.readMessage(modelData.id)
            }

            Kirigami.PlaceholderMessage {
                anchors.centerIn: parent
                visible: messageList.count === 0
                text: {
                    if (!AppMeshBridge.available) return "Mail library not loaded"
                    if (!root.connected) return "Not connected"
                    return "No messages"
                }
                icon.name: root.connected ? "mail-unread" : "network-disconnect"
                helpfulAction: Kirigami.Action {
                    text: root.connected ? "" : "Log in..."
                    icon.name: "network-connect"
                    visible: !root.connected
                    onTriggered: loginDialog.open()
                }
            }
        }
    }

    // --- Message Detail Page ---

    Component {
        id: detailPageComponent

        Kirigami.ScrollablePage {
            id: detailInner
            property var email: ({})

            title: email.subject || "(no subject)"

            actions: [
                Kirigami.Action {
                    text: "Reply"
                    icon.name: "mail-reply-sender"
                    onTriggered: {
                        replySheet.replyToId = detailInner.email.id || ""
                        replySheet.replySubject = detailInner.email.subject || ""
                        replySheet.quotedText = root.quoteText(detailInner.email)
                        replySheet.prepareBody()
                        replySheet.open()
                    }
                },
                Kirigami.Action {
                    text: "Flag"
                    icon.name: "flag"
                    onTriggered: root.flagMessage(detailInner.email.id, false)
                },
                Kirigami.Action {
                    text: "Delete"
                    icon.name: "edit-delete"
                    onTriggered: root.deleteMessage(detailInner.email.id)
                },
                Kirigami.Action {
                    text: "Move..."
                    icon.name: "edit-move"
                    onTriggered: moveSheet.open()
                },
                Kirigami.Action {
                    text: "Mark Unread"
                    icon.name: "mail-mark-unread"
                    onTriggered: {
                        let result = AppMeshBridge.portExecute("mail", "mark_unread", { id: detailInner.email.id })
                        if (result.ok) root.showPassiveNotification(result.ok)
                    }
                }
            ]

            ColumnLayout {
                spacing: Kirigami.Units.largeSpacing

                // Headers — left-aligned grid
                GridLayout {
                    columns: 2
                    columnSpacing: Kirigami.Units.smallSpacing
                    rowSpacing: 4
                    Layout.fillWidth: true

                    QQC2.Label { text: "From:"; font.bold: true; opacity: 0.7 }
                    QQC2.Label { text: detailInner.email.from || ""; wrapMode: Text.Wrap; Layout.fillWidth: true }

                    QQC2.Label { text: "To:"; font.bold: true; opacity: 0.7 }
                    QQC2.Label { text: detailInner.email.to || ""; wrapMode: Text.Wrap; Layout.fillWidth: true }

                    QQC2.Label { text: "Date:"; font.bold: true; opacity: 0.7 }
                    QQC2.Label { text: root.formatDate(detailInner.email.date); Layout.fillWidth: true }

                    QQC2.Label { text: "Subject:"; font.bold: true; opacity: 0.7 }
                    QQC2.Label { text: detailInner.email.subject || ""; font.bold: true; wrapMode: Text.Wrap; Layout.fillWidth: true }
                }

                Kirigami.Separator { Layout.fillWidth: true }

                // Body
                QQC2.Label {
                    text: detailInner.email.body || detailInner.email.preview || ""
                    wrapMode: Text.Wrap
                    Layout.fillWidth: true
                    textFormat: Text.PlainText
                }
            }
        }
    }

    property var detailPage: detailPageComponent.createObject(root)

    // --- Login Dialog ---

    Kirigami.Dialog {
        id: loginDialog
        title: "Connect to Mail Server"
        preferredWidth: Kirigami.Units.gridUnit * 25
        standardButtons: Kirigami.Dialog.NoButton
        closePolicy: root.connected ? Kirigami.Dialog.CloseOnEscape : Kirigami.Dialog.NoAutoClose

        customFooterActions: [
            Kirigami.Action {
                text: "Connect"
                icon.name: "network-connect"
                onTriggered: {
                    loginError.visible = false
                    let ok = root.connectAndLoad(loginUrl.text, loginUser.text, loginPass.text)
                    if (ok) {
                        loginDialog.close()
                    } else {
                        loginError.visible = true
                    }
                }
            }
        ]

        ColumnLayout {
            spacing: Kirigami.Units.smallSpacing

            Kirigami.Icon {
                source: "mail-client"
                Layout.preferredWidth: Kirigami.Units.iconSizes.huge
                Layout.preferredHeight: Kirigami.Units.iconSizes.huge
                Layout.alignment: Qt.AlignHCenter
            }

            QQC2.Label {
                text: "Enter your JMAP server credentials"
                Layout.alignment: Qt.AlignHCenter
                opacity: 0.7
            }

            QQC2.TextField {
                id: loginUrl
                Layout.fillWidth: true
                placeholderText: "JMAP Server URL"
                text: "https://mail.goldcoast.org:8443"
            }
            QQC2.TextField {
                id: loginUser
                Layout.fillWidth: true
                placeholderText: "Email / Username"
            }
            QQC2.TextField {
                id: loginPass
                Layout.fillWidth: true
                placeholderText: "Password"
                echoMode: TextInput.Password
                onAccepted: {
                    loginError.visible = false
                    let ok = root.connectAndLoad(loginUrl.text, loginUser.text, loginPass.text)
                    if (ok) loginDialog.close()
                    else loginError.visible = true
                }
            }

            QQC2.Label {
                id: loginError
                text: "Connection failed — check URL and credentials"
                color: Kirigami.Theme.negativeTextColor
                visible: false
                Layout.alignment: Qt.AlignHCenter
            }
        }
    }

    // --- Compose Sheet ---

    Kirigami.Dialog {
        id: composeSheet
        title: "Compose"
        preferredWidth: Kirigami.Units.gridUnit * 30
        preferredHeight: Kirigami.Units.gridUnit * 25
        standardButtons: Kirigami.Dialog.NoButton

        customFooterActions: [
            Kirigami.Action {
                text: "Send"
                icon.name: "mail-send"
                onTriggered: root.sendMail(composeTo.text, composeSubject.text, composeBody.text)
            },
            Kirigami.Action {
                text: "Cancel"
                icon.name: "dialog-cancel"
                onTriggered: composeSheet.close()
            }
        ]

        ColumnLayout {
            spacing: Kirigami.Units.smallSpacing

            QQC2.TextField {
                id: composeTo
                Layout.fillWidth: true
                placeholderText: "To"
            }
            QQC2.TextField {
                id: composeSubject
                Layout.fillWidth: true
                placeholderText: "Subject"
            }
            QQC2.ScrollView {
                Layout.fillWidth: true
                Layout.fillHeight: true

                QQC2.TextArea {
                    id: composeBody
                    placeholderText: "Message body..."
                    wrapMode: TextEdit.Wrap
                }
            }
        }

        onClosed: {
            composeTo.text = ""
            composeSubject.text = ""
            composeBody.text = ""
        }
    }

    // --- Reply Sheet ---

    Kirigami.Dialog {
        id: replySheet
        title: "Reply"
        preferredWidth: Kirigami.Units.gridUnit * 35
        preferredHeight: Kirigami.Units.gridUnit * 28
        standardButtons: Kirigami.Dialog.NoButton

        property string replyToId: ""
        property string replySubject: ""
        property string quotedText: ""

        function prepareBody() {
            if (root.replyAboveQuote) {
                replyBody.text = "\n\n" + quotedText
                replyBody.cursorPosition = 0
            } else {
                replyBody.text = quotedText + "\n\n"
                replyBody.cursorPosition = replyBody.text.length
            }
        }

        customFooterActions: [
            Kirigami.Action {
                text: "Send Reply"
                icon.name: "mail-reply-sender"
                onTriggered: root.replyToMessage(replySheet.replyToId, replyBody.text)
            },
            Kirigami.Action {
                text: "Cancel"
                icon.name: "dialog-cancel"
                onTriggered: replySheet.close()
            }
        ]

        ColumnLayout {
            spacing: Kirigami.Units.smallSpacing

            RowLayout {
                Layout.fillWidth: true

                QQC2.Label {
                    text: "Re: " + replySheet.replySubject
                    font.bold: true
                    elide: Text.ElideRight
                    Layout.fillWidth: true
                }

                QQC2.Label {
                    text: "Reply position:"
                    opacity: 0.7
                }
                QQC2.ComboBox {
                    id: replyPosition
                    model: ["Above quote", "Below quote"]
                    currentIndex: root.replyAboveQuote ? 0 : 1
                    onCurrentIndexChanged: {
                        root.replyAboveQuote = (currentIndex === 0)
                        replySheet.prepareBody()
                    }
                }
            }

            QQC2.ScrollView {
                Layout.fillWidth: true
                Layout.fillHeight: true

                QQC2.TextArea {
                    id: replyBody
                    wrapMode: TextEdit.Wrap
                    font.family: "monospace"
                }
            }
        }

        onClosed: replyBody.text = ""
    }

    // --- Move Sheet ---

    Kirigami.Dialog {
        id: moveSheet
        title: "Move to..."
        preferredWidth: Kirigami.Units.gridUnit * 20
        standardButtons: Kirigami.Dialog.Cancel

        ListView {
            implicitHeight: Kirigami.Units.gridUnit * 15
            model: root.mailboxes

            delegate: QQC2.ItemDelegate {
                required property var modelData
                width: ListView.view.width
                text: modelData.name || "Unknown"
                icon.name: "folder-mail"
                onClicked: {
                    root.moveMessage(detailPage.email.id, modelData.name)
                    moveSheet.close()
                }
            }
        }
    }
}
