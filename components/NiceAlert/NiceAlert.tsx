import React from "react";
import { Modal, View, Text, Pressable} from "react-native";

type Props = {
    visible: boolean;         
    title?: string;           
    message: string;          
    onClose: () => void;      
};

export default function NiceAlert ({
    visible,
    title = "Ocorreu um erro",
    message,
    onClose,
}: Props) {
    const errorClasses = "bg-red-100 dark:bg-red-900/40 border-red-300";
    return (
        <Modal
            animationType = "fade"
            transparent
            visible={visible}
            onRequestClose = {onClose}
        >
            <View className="flex-1 items-center justify-center bg-back/40 px-6">
                <View className="w-full max-w-md rounded-2xl bg-white dark:bg-neutral-900 p-6 shadow-2xl border border-neutral-200 dark:border-neutral-800">
                    <View className={`mb-4 rounded-xl border px-3 py-2 ${errorClasses}`}>
                        <Text className="text-lg font-semibold dark:text-white">
                            {title}
                        </Text>
                    </View>

                    <Text className="text-base text-neutral-700 dark:text-neutral-300">
                        {message}
                    </Text>

                    <View className="mt-6 flex-row justify-end gap-3">
                        <Pressable
                            accessibilityRole="button"
                            onPress={onClose}
                            className="h-11 rounded-xl bg-neutral-200 dark:bg-neutral-800 px-5 items-center justify-center"
                        >
                        <Text className="font-medium dark:text-white">Fechar</Text>
                        </Pressable> 
                    </View>
                    
                </View>
            </View>
        </Modal>
    )
}