import fs from "fs-extra";
import path from "path";
import cliColor from "cli-color";
import emojic from "emojic";
import archiver from "archiver";

// Dynamic paths
const packagePath = path.resolve("./"); // plugin root
const packageSlug = path.basename(packagePath);
const distDir = path.join(packagePath, "dist");

// Files/folders to include in final package
let includes = [
    "app",
    "assets",
    "languages",
    "vendor_prefixed",
    "autoload.php",
    "index.php",
    "README.txt",
    "composer.json",
    `${packageSlug}.php`,
];

/**
 * Extract version from plugin main file
 */
async function getVersion() {
    try {
        const file = await fs.readFile(path.join(packagePath, `${packageSlug}.php`), "utf8");
        const lines = file.split(/\r?\n/);

        for (const line of lines) {
            if (line.includes("* Version:") || line.includes("*Version:")) {
                return line.replace("* Version:", "").replace("*Version:", "").trim();
            }
        }
    } catch (e) {
        console.error("Could not read version:", e);
    }

    return "0.0.0";
}

/**
 * Copy selected files/folders to dist/plugin-name
 */
async function runPackage() {

    // Ensure main dist folder exists
    await fs.ensureDir(distDir);

    const destination = path.join(distDir, packageSlug);
    await fs.ensureDir(destination);

    console.log(cliColor.cyan(`Packaging → ${destination}`));

    for (const item of includes) {
        const from = path.join(packagePath, item);
        const to = path.join(destination, item);

        try {
            await fs.copy(from, to);
            console.log(cliColor.white(`=> ${emojic.smiley}  ${item} copied...`));
        } catch (err) {
            console.error(`Copy failed for ${item}:`, err);
        }
    }

    console.log(cliColor.green(`=> ${emojic.whiteCheckMark} Build directory created`));
}

/**
 * Create zip file plugin-name-{version}.zip
 */
async function runZip() {
    // Ensure dist/plugin-name exists first
    const buildFolder = path.join(distDir, packageSlug);
    const exists = await fs.pathExists(buildFolder);
    if (!exists) {
        console.log(cliColor.yellow(`Build folder not found, creating package first...`));
        await runPackage();
    }

    const version = await getVersion();
    const zipName = `${packageSlug}-${version}.zip`;
    const destinationPath = path.join(distDir, zipName);

    console.log(cliColor.cyan(`Zipping → ${zipName}`));

    const output = fs.createWriteStream(destinationPath);
    const archive = archiver("zip", { zlib: { level: 9 } });

    return new Promise((resolve, reject) => {
        output.on("close", () => {
            console.log(cliColor.green(`=> ZIP created successfully: ${zipName} (${archive.pointer()} bytes)`));
            resolve();
        });

        archive.on("error", (err) => reject(err));

        archive.pipe(output);
        archive.directory(buildFolder, packageSlug);
        archive.finalize();
    });
}

/**
 * Main handler
 */
async function main() {
    const mode = process.env.NODE_ENV;
    if (mode === "package") {
        await runPackage();
        return;
    }
    if (mode === "zip") {
        await runZip();
        return;
    }
    console.log(mode, cliColor.yellow("Nothing to run. Set NODE_ENV=package or NODE_ENV=zip"));
}

main();
