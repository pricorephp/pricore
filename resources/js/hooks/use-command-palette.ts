import { createContext, useContext } from 'react';

interface CommandPaletteContextType {
    open: boolean;
    setOpen: (open: boolean) => void;
}

export const CommandPaletteContext = createContext<CommandPaletteContextType>({
    open: false,
    setOpen: () => {},
});

export function useCommandPalette(): CommandPaletteContextType {
    return useContext(CommandPaletteContext);
}
