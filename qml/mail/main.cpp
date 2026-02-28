#include <QApplication>
#include <QQmlApplicationEngine>
#include <QDir>
#include <QIcon>

int main(int argc, char *argv[])
{
    QApplication app(argc, argv);
    app.setApplicationName("AppMesh Mail");
    app.setOrganizationDomain("appmesh.nexus");
    app.setWindowIcon(QIcon::fromTheme("mail-client"));

    QQmlApplicationEngine engine;

    // Find AppMesh plugin: build tree first, then installed location
    engine.addImportPath(QCoreApplication::applicationDirPath());
    engine.addImportPath(QDir::homePath() + QStringLiteral("/.local/lib/qt6/qml"));

    engine.loadFromModule("AppMeshMail", "Main");
    return app.exec();
}
