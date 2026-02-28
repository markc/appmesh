#include <QGuiApplication>
#include <QQmlApplicationEngine>
#include <QDir>

int main(int argc, char *argv[])
{
    QGuiApplication app(argc, argv);
    QQmlApplicationEngine engine;

    // Find plugin: build tree first, then installed location
    engine.addImportPath(QCoreApplication::applicationDirPath());
    engine.addImportPath(QDir::homePath() + QStringLiteral("/.local/lib/qt6/qml"));

    engine.loadFromModule("AppMeshApp", "Main");
    return app.exec();
}
