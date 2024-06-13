export enum LogLevel {
    ERROR = 'ERROR',
    INFO = 'INFO',
    WARNING = 'WARNING',
    NOTICE = 'NOTICE',
    DEBUG = 'DEBUG',
}

export enum SelectLogFieldEnum {
    Context = 'context',
    Backtrace = 'backtrace',
}

export interface ILogBacktrace {
    file: string
    line: number
    function: string
    class?: string
    type?: string
    args?: string[]
}

export interface ILog {
    timestamp: string
    level: LogLevel
    message: string
    context: object
    backtrace: ILogBacktrace[]
    select?: SelectLogFieldEnum
}

export interface ILogFile {
    name: string
    url: string
    created: string
    modified: string
    size: number
}

export interface ILogTab {
    id: string
    filters: []
    activeFile?: ILogFile
    files: ILogFile[]
    logs: ILog[]
    openLogs: number[]
}
