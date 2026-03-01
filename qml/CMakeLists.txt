cmake_minimum_required(VERSION 3.28)
project(appmesh-qml VERSION 0.1 LANGUAGES CXX)

set(CMAKE_CXX_STANDARD 20)
set(CMAKE_CXX_STANDARD_REQUIRED ON)
set(CMAKE_AUTOMOC ON)

find_package(Qt6 REQUIRED COMPONENTS Core Qml Quick Widgets)
find_package(KF6Kirigami)
qt_standard_project_setup(REQUIRES 6.5)

# --- Shared QML plugin library ---
qt_add_qml_module(appmeshplugin
    URI AppMesh
    VERSION 1.0
    SHARED
    SOURCES appmeshplugin.h appmeshplugin.cpp
    OUTPUT_DIRECTORY ${CMAKE_BINARY_DIR}/AppMesh
)

target_link_libraries(appmeshplugin PRIVATE Qt6::Core Qt6::Qml Qt6::Quick dl)
target_include_directories(appmeshplugin PRIVATE ${CMAKE_SOURCE_DIR}/../crates/appmesh-core)

# Place the backing .so next to qmldir so the QML engine finds it at runtime
set_target_properties(appmeshplugin PROPERTIES
    LIBRARY_OUTPUT_DIRECTORY ${CMAKE_BINARY_DIR}/AppMesh
)

# --- Standalone test app ---
qt_add_executable(appmesh-app main.cpp)
qt_add_qml_module(appmesh-app
    URI AppMeshApp
    VERSION 1.0
    QML_FILES Main.qml
    NO_RESOURCE_TARGET_PATH
)

target_link_libraries(appmesh-app PRIVATE Qt6::Core Qt6::Qml Qt6::Quick)

# --- Install targets ---
# Query Qt for the system QML path so plasmawindowed/plasmashell can find the module
execute_process(COMMAND qmake6 -query QT_INSTALL_QML OUTPUT_VARIABLE QT_QML_DIR OUTPUT_STRIP_TRAILING_WHITESPACE)
set(QML_INSTALL_DIR "${QT_QML_DIR}/AppMesh")
set(PLASMOID_INSTALL_DIR "$ENV{HOME}/.local/share/plasma/plasmoids")

install(
    DIRECTORY ${CMAKE_BINARY_DIR}/AppMesh/
    DESTINATION ${QML_INSTALL_DIR}
    FILES_MATCHING PATTERN "*.so" PATTERN "qmldir" PATTERN "*.qmltypes"
)
install(FILES ${CMAKE_BINARY_DIR}/libappmeshplugin.so DESTINATION ${QML_INSTALL_DIR})

install(DIRECTORY ${CMAKE_SOURCE_DIR}/plasmoids/com.appmesh.send
    DESTINATION ${PLASMOID_INSTALL_DIR})
install(DIRECTORY ${CMAKE_SOURCE_DIR}/plasmoids/com.appmesh.log
    DESTINATION ${PLASMOID_INSTALL_DIR})

# --- Pure Qt Quick mail client (Thunderbird-style) ---
qt_add_executable(appmesh-mail mail/main.cpp)
qt_add_qml_module(appmesh-mail
    URI AppMeshMail
    VERSION 1.0
    QML_FILES mail/Main.qml
    NO_RESOURCE_TARGET_PATH
)

target_link_libraries(appmesh-mail PRIVATE
    Qt6::Core Qt6::Qml Qt6::Quick Qt6::Widgets
)

# --- Kirigami mail client (preserved) ---
if(KF6Kirigami_FOUND)
    qt_add_executable(appmesh-mail-kirigami mail-kirigami/main.cpp)
    qt_add_qml_module(appmesh-mail-kirigami
        URI AppMeshMailKirigami
        VERSION 1.0
        QML_FILES mail-kirigami/Main.qml
        NO_RESOURCE_TARGET_PATH
    )

    target_link_libraries(appmesh-mail-kirigami PRIVATE
        Qt6::Core Qt6::Qml Qt6::Quick Qt6::Widgets KF6::Kirigami
    )
endif()
