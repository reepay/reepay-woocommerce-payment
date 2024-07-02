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

export interface IDefaultLog {
    timestamp: string
    level: LogLevel
    message: string
    backtrace: ILogBacktrace[]
    context: object
}

export interface ILog extends IDefaultLog {
    select?: SelectLogFieldEnum
    id: string
}

export interface ILogFile {
    name: string
    url: string
    path: string
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
    openLogs: string[]
}
