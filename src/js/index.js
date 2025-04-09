import schedule from "../schedule-data/first-sem-schedule.json" with { type: "json" };
import { SheetAPI } from "./sheet-api.js";

console.log(await SheetAPI.getNames());




